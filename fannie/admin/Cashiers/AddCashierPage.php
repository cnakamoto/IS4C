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

class AddCashierPage extends FanniePage 
{

    protected $title = "Fannie : Add Cashier";
    protected $header = "Add Cashier";
    protected $must_authenticate = True;
    protected $auth_classes = array('editcashiers');

    public $description = '[Add Cashier] is the tool to create new cashiers.';
    public $themed = true;

    function preprocess()
    {
        global $FANNIE_OP_DB;
        if (FormLib::get_form_value('fname') !== '')
        {
            $fn = FormLib::get_form_value('fname');
            $ln = FormLib::get_form_value('lname');
            $fes = FormLib::get_form_value('fes');

            $dbc = FannieDB::get($FANNIE_OP_DB);
            
            $passwd = '';
            srand();
            $checkP = $dbc->prepare_statement("SELECT * FROM employees WHERE CashierPassword=?");
            while($passwd == ''){
                $newpass = rand(1000,9999);
                $checkR = $dbc->exec_statement($checkP,array($newpass));
                if ($dbc->num_rows($checkR) == 0)
                    $passwd = $newpass;
            }

            $idQ = $dbc->prepare_statement("SELECT max(emp_no)+1 FROM employees WHERE emp_no < 1000");
            $idR = $dbc->exec_statement($idQ);
            $emp_no = array_pop($dbc->fetch_row($idR));
            if ($emp_no == '') $emp_no=1;

            $employee = new EmployeesModel($dbc);
            $employee->emp_no($emp_no);
            $employee->CashierPassword($passwd);
            $employee->AdminPassword($passwd);
            $employee->FirstName($fn);
            $employee->LastName($ln);
            $employee->JobTitle('');
            $employee->EmpActive(1);
            $employee->frontendsecurity($fes);
            $employee->backendsecurity($fes);
            $employee->save();

            $message = sprintf("Cashier Created<br />Name:%s<br />Emp#:%d<br />Password:%d",
                $fn.' '.$ln,$emp_no,$passwd);
            $this->add_onload_command("showBootstrapAlert('#alert-area', 'success', '$message');\n");
        }

        return true;
    }

    function body_content()
    {
        ob_start();
        ?>
        <div id="alert-area"></div>
        <form action="AddCashierPage.php" method="post">
        <label>First Name</label>
        <input type=text name=fname required class="form-control" />
        <label>Last Name</label>
        <input type=text name=lname required class="form-control" />
        <label>Privileges</label>
        <select name="fes" class="form-control">
            <option value=20>Regular</option>
            <option value=30>Manager</option>
        </select>
        <p>
            <button type="submit" class="btn btn-default">Create Cashier</button>
        </p>
        </form>
        <?php
        $ret .= ob_get_clean();
        return $ret;
    }
}

FannieDispatch::conditionalExec(false);

?>
