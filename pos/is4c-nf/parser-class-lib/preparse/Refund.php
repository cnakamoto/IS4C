<?php
/*******************************************************************************

    Copyright 2007 Whole Foods Co-op

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

class Refund extends Parser {
	
	function check($str){
		if (substr($str,0,2) == "RF")
			return True;
		elseif (substr($str,-2) == "RF")
			return True;
		return False;
	}

	function parse($str){
		global $CORE_LOCAL;
		$remainder = "";
		if (substr($str,0,2) == "RF")
			$remainder = substr($str,2);
		else
			$remainder = substr($str,0,-2);
		if (!is_numeric($remainder) &&
		    !strpos($remainder,"*") &&
		    !strpos($remainder,"DP"))
			$remainder = "";
		else {
			$CORE_LOCAL->set("refund",1);
		}
		twoPairs();
		return $remainder;
	}

	function doc(){
		return "<table cellspacing=0 cellpadding=3 border=1>
			<tr>
				<th>Input</th><th>Result</th>
			</tr>
			<tr>
				<td>RF<i>ringable</i>
				OR <i>ringable</i>RF
				</td>
				<td>Refund the specified item(s). <i>Ringable
				</i> can be a single UPC, an open-department
				ring, or a multiple using *</td>
			</tr>
			</table>";
	}
}

?>
