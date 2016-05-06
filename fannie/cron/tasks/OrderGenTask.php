<?php
/*******************************************************************************

    Copyright 2015 Whole Foods Co-op

    This file is part of CORE-POS.

    CORE-POS is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    CORE-POS is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

class OrderGenTask extends FannieTask
{

    public $name = 'Auto Order';

    public $description = 'Generates orders based on inventory info';

    public $default_schedule = array(
        'min' => 45,
        'hour' => 3,
        'day' => '*',
        'month' => '*',
        'weekday' => '*',
    );

    private $silent = false;
    public function setSilent($s)
    {
        $this->silent = $s;
    }

    private $vendors = array();
    public function setVendors($v)
    {
        $this->vendors = $v;
    }

    private $store = 0;
    public function setStore($s)
    {
        $this->store = $s;
    }

    private function freshenCache($dbc)
    {
        $items = $dbc->query('
            SELECT i.upc,
                i.storeID,
                i.count,
                i.countDate
            FROM InventoryCounts AS i
                LEFT JOIN InventoryCache AS c ON i.upc=c.upc AND i.storeID=c.storeID
            WHERE i.mostRecent=1
                AND c.upc IS NULL'); 
        $ins = $dbc->prepare('
            INSERT INTO InventoryCache
                (upc, storeID, cacheStart, cacheEnd, baseCount, ordered, sold, shrunk, onHand)
            VALUES (?, ?, ?, ?, ?, 0, 0, 0, ?)');
        while ($row = $dbc->fetchRow($items)) {
            $args = array($row['upc'], $row['storeID'], $row['countDate'], $row['countDate'], $row['count'], $row['count']);
            $dbc->execute($ins, $args);
        }
    }

    public function run()
    {
        $dbc = FannieDB::get($this->config->get('OP_DB'));
        $this->freshenCache($dbc);
        $curP = $dbc->prepare('SELECT onHand,cacheEnd FROM InventoryCache WHERE upc=? AND storeID=? AND baseCount >= 0');
        $catalogP = $dbc->prepare('SELECT * FROM vendorItems WHERE upc=? AND vendorID=?');
        $costP = $dbc->prepare('SELECT cost FROM products WHERE upc=? AND store_id=?');
        $prodP = $dbc->prepare('SELECT * FROM products WHERE upc=? AND store_id=?');
        $dtP = $dbc->prepare('
            SELECT ' . DTrans::sumQuantity() . '
            FROM ' . $this->config->get('TRANS_DB') . $dbc->sep() . 'dlog
            WHERE tdate > ?
                AND upc=?
                AND store_id=?
                AND trans_status <> \'R\'');
        $shP = $dbc->prepare('
            SELECT ' . DTrans::sumQuantity() . '
            FROM ' . $this->config->get('TRANS_DB') . $dbc->sep() . 'dtransactions
            WHERE tdate > ?
                AND upc=?
                AND store_id=?
                AND trans_status = \'Z\'
                AND emp_no <> 9999
                AND register_no <> 99');
        /**
          Look up all items that have a count and
          compare current [estimated] inventory to
          the par value
        */
        list($inStr, $args) = $dbc->safeInClause($this->vendors);
        if ($this->store != 0) {
            $args[] = $this->store;
        }
        $prep = $dbc->prepare('
            SELECT i.upc,
                i.storeID,
                i.par,
                p.default_vendor_id AS vid
            FROM InventoryCounts AS i
                INNER JOIN products AS p ON i.upc=p.upc AND i.storeID=p.store_id
            WHERE i.mostRecent=1
                AND p.default_vendor_id IN (' . $inStr . ')
                ' . ($this->store != 0 ? ' AND i.storeID=? ' : '') . '
            ORDER BY p.default_vendor_id, i.upc, i.storeID, i.countDate DESC');
        $res = $dbc->execute($prep, $args);
        $orders = array();
        while ($row = $dbc->fetchRow($res)) {
            $cache = $dbc->getRow($curP, array($row['upc'],$row['storeID']));
            if ($cache === false) {
                continue;
            }
            $sales = $dbc->getValue($dtP, array($cache['cacheEnd'], $row['upc'], $row['storeID']));
            $cur = $sales ? $cache['onHand'] - $sales : $cache['onHand'];
            $shrink = $dbc->getValue($shP, array($cache['cacheEnd'], $row['upc'], $row['storeID']));
            $cur = $shrink ? $cur - $shrink : $cur;
            if ($cur !== false && $cur < $row['par']) {
                /**
                  Allocate a purchase order to hold this vendors'
                  item(s)
                */
                if (!isset($orders[$row['vid'].'-'.$row['storeID']])) {
                    $order = new PurchaseOrderModel($dbc);
                    $order->vendorID($row['vid']);
                    $order->creationDate(date('Y-m-d H:i:s'));
                    $order->storeID($row['storeID']);
                    $poID = $order->save();
                    $orders[$row['vid'].'-'.$row['storeID']] = $poID;
                }
                $itemR = $dbc->getRow($catalogP, array($row['upc'], $row['vid']));

                // If the item is a breakdown, get its source package
                // and multiply the case size to reflect total brokendown units
                $bdInfo = COREPOS\Fannie\API\item\InventoryLib::isBreakdown($dbc, $row['upc']);
                if ($bdInfo) {
                    $itemR2 = $dbc->getRow($catalogP, array($bdInfo['upc'], $row['vid']));
                    if ($itemR2) {
                        $itemR = $itemR2;
                        $itemR['units'] *= $bdInfo['units'];
                    }
                }

                // no catalog entry to create an order
                if ($itemR === false || $itemR['units'] <= 0) {
                    $itemR['sku'] = $row['upc'];
                    $prodW = $dbc->getRow($prodP, array($row['upc'], $row['storeID']));
                    if ($prodW === false) {
                        continue;
                    } else {
                        $itemR['brand'] = $prodW['brand'];
                        $itemR['description'] = $prodW['description'];
                        $itemR['cost'] = $prodW['cost'];
                        $itemR['saleCost'] = 0;
                        $itemR['size'] = $prodW['size'];
                        $itemR['units'] = 1;
                    }
                }

                /**
                  Determine cases required to reach par again
                  and add to order
                */
                $cases = 1;
                while (($cases*$itemR['units']) + $cur < $row['par']) {
                    $cases++;
                }
                $poi = new PurchaseOrderItemsModel($dbc);
                $poi->orderID($orders[$row['vid'].'-'.$row['storeID']]);
                $poi->sku($itemR['sku']);
                $poi->quantity($cases);
                $poi->unitCost($itemR['saleCost'] ? $itemR['saleCost'] : $itemR['cost']);
                if ($poi->unitCost() == 0) {
                    $cost = $dbc->getValue($costP, array($row['upc'], $row['storeID']));
                    $poi->unitCost($cost);
                }
                $poi->caseSize($itemR['units']);
                $poi->unitSize($itemR['size']);
                $poi->brand($itemR['brand']);
                $poi->description($itemR['description']);
                $poi->internalUPC($row['upc']);
                $poi->save();
            }
        }

        if (!$this->silent) {
            $this->sendNotifications($dbc, $orders);
        }
    }

    private function sendNotifications($dbc, $orders)
    {
        /**
          Fire off email notifications
        */
        $deptP = $dbc->prepare('
            SELECT e.emailAddress
            FROM PurchaseOrderItems AS i
                INNER JOIN products AS p ON p.upc=i.internalUPC
                INNER JOIN superdepts AS s ON p.department=s.dept_ID
                INNER JOIN superDeptEmails AS e ON s.superID=e.superID
            WHERE orderID=?
            GROUP BY e.emailAddress');
        foreach ($orders as $oid) {
            $sendTo = array();
            $deptR = $dbc->execute($deptP, array($oid));
            while ($deptW = $dbc->fetchRow($deptR)) {
                $sendTo[] = $deptW['emailAddress'];
            }
            $sendTo = 'andy@wholefoods.coop';
            if (count($sendTo) > 0) {
                $msg_body = 'Created new order' . "\n";
                $msg_body .= "http://" . 'key' . '/' . $this->config->get('URL')
                    . 'purchasing/ViewPurchaseOrders.php?id=' . $oid . "\n";
                mail($sendTo, 'Generated Purchase Order', $msg_body);
            }
        }
    }
}

