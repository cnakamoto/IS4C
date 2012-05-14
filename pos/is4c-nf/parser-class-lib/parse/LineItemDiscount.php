<?php
/*******************************************************************************

    Copyright 2012 Whole Foods Co-op

    This file is part of IT CORE.

    IT CORE is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    IT CORE is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

$CORE_PATH = isset($CORE_PATH)?$CORE_PATH:"";
if (empty($CORE_PATH)){ while(!file_exists($CORE_PATH."pos.css")) $CORE_PATH .= "../"; }

class LineItemDiscount extends Parser {

	/* Parse module matches input LD */
	function check($str){
		if ($str == "LD")
			return True;
		return False;
	}

	function parse($str){
		global $CORE_LOCAL,$CORE_PATH;

		$ret = $this->default_json();

		// this is the currently selected item
		$transID = $CORE_LOCAL->get("currentid");

		// get info about the current item
		$db = Database::tDataConnect();
		$q = "SELECT trans_type,discounttype,department,regPrice,quantity 
			FROM localtemptrans WHERE trans_id=".((int)$transID);
		$r = $db->query($q);

		if ($db->num_rows($r) == 0){
			// this shouldn't happen unless there's some weird session problem
			$ret['output'] = DisplayLib::boxMsg("Item not found");
		}
		else {
			$w = $db->fetch_row($r);
			if ($w['trans_type'] != 'I' && $w['trans_type'] != 'D'){
				// only items & open rings are discountable
				$ret['output'] = DisplayLib::boxMsg("Line is not discountable");
			}
			else if ($w['discounttype'] != 0){
				// for simplicity, sale items cannot be discounted
				// this also prevents using this function more than
				// once on a single item
				$ret['output'] = DisplayLib::boxMsg("Item already discounted");
			}
			else {
				// discount is simply the total times the 
				//   non-member discount percentage
				// total is discounted immediately using
				//   the non-member percentage
				// memDiscount is the difference between total
				//   member discount and the non-member discount
				//   since the non-member discount is applied 
				//   immediately
				// setting discounttype=2 makes the member discount
				//   apply when a [valid] member number is entered
				$discQ = sprintf("UPDATE localtemptrans SET
					discount=(regPrice * quantity * %f), 
					total=(total-(regPrice*quantity*%f)),
					memDiscount=((regPrice*quantity*%f) - (regPrice*quantity*%f)),
					discounttype=2
					WHERE trans_id=%d",
					$CORE_LOCAL->get("LineItemDiscountNonMem"),
					$CORE_LOCAL->get("LineItemDiscountNonMem"),
					$CORE_LOCAL->get("LineItemDiscountMem"),
					$CORE_LOCAL->get("LineItemDiscountNonMem"),
					$transID);
				$discR = $db->query($discQ);

				// add notification line for nonMem discount
				TransRecord::adddiscount($w['regPrice']*$w['quantity']*$CORE_LOCAL->get("LineItemDiscountNonMem"),
					$w['department']);

				// footer should be redrawn since savings and totals
				// have changed. Output is the list of items
				$ret['redraw_footer'] = True;
				$ret['output'] = DisplayLib::lastpage();
			}
		}

		return $ret;
	}

	function doc(){
		return "<table cellspacing=0 cellpadding=3 border=1>
			<tr>
				<th>Input</th><th>Result</th>
			</tr>
			<tr>
				<td>LD</td>
				<td>Apply line item percent discount based on membership status</td>
			</tr>
			</table>";
	}

}

?>
