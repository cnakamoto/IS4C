<?php
/*******************************************************************************

    Copyright 2009 Whole Foods Co-op

    This file is part of Fannie.

    Fannie is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    Fannie is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

include(dirname(__FILE__) . '/../../config.php');
if (!class_exists('FannieAPI')) {
    include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class CashierEditor extends FanniePage {

    protected $title = "Fannie : Edit Cashier";
    protected $header = "Edit Cashier";
    protected $must_authenticate = True;
    protected $auth_classes = array('editcashiers');

    public $description = '[Edit Cashier] is for managing existing cashiers.';
    public $themed = true;

    private $messages = '';

    function preprocess(){
        global $FANNIE_OP_DB;
        $emp_no = FormLib::get_form_value('emp_no',0);

        if (FormLib::get_form_value('fname') !== '') {
            $fn = FormLib::get_form_value('fname');
            $ln = FormLib::get_form_value('lname');
            $passwd = FormLib::get_form_value('passwd');
            $fes = FormLib::get_form_value('fes');
            $active = FormLib::get_form_value('active') !== '' ? 1 : 0;

            $dbc = FannieDB::get($FANNIE_OP_DB);
            $employee = new EmployeesModel($dbc);
            $employee->emp_no($emp_no);
            $employee->FirstName($fn);
            $employee->LastName($ln);
            $employee->CashierPassword($passwd);
            $employee->AdminPassword($passwd);
            $employee->frontendsecurity($fes);
            $employee->backendsecurity($fes);
            $employee->EmpActive($active);
            $saved = $employee->save();

            if ($saved) {
                $message = "Cashier Updated. <a href=ViewCashiersPage.php>Back to List of Cashiers</a>";
                $this->add_onload_command("showBootstrapAlert('#alert-area', 'success', '$message');\n");
            } else {
                $this->add_onload_command("showBootstrapAlert('#alert-area', 'danger', 'Error saving cashier');\n");
            }
        }

        return true;
    }

    function body_content()
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $ret = '';
        if (!empty($this->messages)){
            $ret .= '<blockquote style="background: solid 1x black; 
                padding: 5px; margin: 5px;">';
            $ret .= $this->messages;
            $ret .= '</blockquote>';
        }   

        $emp_no = FormLib::get_form_value('emp_no',0);
        $employee = new EmployeesModel($dbc);
        $employee->emp_no($emp_no);
        $employee->load();

        ob_start();
        ?>
        <div id="alert-area"></div>
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
        <label>First Name</label>
        <input type="text" name="fname" value="<?php echo $employee->FirstName(); ?>"
            class="form-control" required />
        <label>Last Name</label>
        <input type="text" name="lname" value="<?php echo $employee->LastName(); ?>"
            class="form-control" />
        <label>Password</label>
        <input type="text" name="passwd" value="<?php echo $employee->CashierPassword(); ?>"
            class="form-control" required />
        <label>Privileges</label>
        <select name="fes" class="form-control">
        <option value="20" <?php echo $employee->frontendsecurity() <= 20 ? 'selected' : '' ?>>Regular</option>
        <option value="30" <?php echo $employee->frontendsecurity() > 20 ? 'selected' : '' ?>>Manager</option>
        </select>
        <label>Active
            <input type="checkbox" name="active" class="checkbox-inline"
                <?php echo $employee->EmpActive()==1 ? 'checked' : ''; ?> />
        </label>
        <p>
            <button type="submit" class="btn btn-default">Save</button>
            <button type="button" class="btn btn-default"
                onclick="location='ViewCashiersPage.php';return false;">Back</button>
        </p>
        <input type="hidden" name="emp_no" value="<?php echo $emp_no; ?>" />
        </form>
        <?php

        return ob_get_clean();
    }
}

FannieDispatch::conditionalExec(false);

?>
