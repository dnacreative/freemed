<?php
 // $Id$
 // desc: module prototype
 // lic : GPL, v2

if (!defined("__MEDICAREMCSIFORMS_MODULE_PHP__")) {

define (__MEDICAREMCSIFORMS_MODULE_PHP__, true);

// class MedicareMCSIFormsModule extends freemedModule
class MedicareMCSIFormsModule extends freemedBillingModule {

	// override variables
	var $MODULE_NAME = "MCSI NSF Medicare";
	var $MODULE_VERSION = "0.1";

	var $PACKAGE_MINIMUM_VERSION = "0.2.1";

	var $CATEGORY_NAME = "Billing";
	var $CATEGORY_VERSION = "0";

	var $ins_upin = array("HCPHM", "HCPMC", "19572");
	var $ins_medicaid = array("26374", "26375", "MSC33", "SET22", "SPH11", "88833");
	var $ins_commercial = array("MSC11","MSC22","MSC33","88811","88822","88833","75201","19572","94999");
    var $form_buffer;
    var $pat_processed;
    var $patient_forms;
    var $patient_cov;
	var $rendorform_variables = array(
		"ba0",
		"ba1",
		"ca0",
		"cb0",
		"da0",
		"da1",
		"da2",
		"ea0",
		"ea1",
		"fa0",
		"fb0",
		"fb1",
		"ha0",
		"xa0",
		"ya0"
		);

	// contructor method
	function MedicareMCSIFormsModule ($nullvar = "") {
		// call parent constructor
		$this->freemedBillingModule($nullvar);
	} // end function MedicareFormsModule

	// override check_vars method
	function check_vars ($nullvar = "") {
		global $module;
		if (!isset($module)) return false;
		return true;
	} // end function check_vars

	// override main function

	function addform()
	{
		reset ($GLOBALS);
		while (list($k,$v)=each($GLOBALS)) global $$k;

		if (!$been_here)
		{
			$this->view();
			return;
		}
		if ($viewaction=="geninsform")
		{
		    $this->form_buffer = "";
			$this->pat_processed = 0;
			if ($bill_request_type > 0)
			{
				$query = "SELECT covpatient,id FROM coverage WHERE covtype='$bill_request_type'";
				$result = $sql->query($query);
				if (!$sql->results($result)) 
				{
					DIE("No patients with this coverage type");
				}
			
				while($row = $sql->fetch_array($result))
				{	
					$coverage = new Coverage($row[id]);
					if (!$coverage)
						DIE("Failed getting coverage");
					// commercial insurers only
					if ($coverage->covinsco) 
					{
						$insmod = freemed_get_link_rec($coverage->covinsco->modifiers[0],"insmod");
						if (!$insmod)
							DIE("Failed getting insurance modifier");
						if ($insmod[insmod] != "MC")
							continue;
					}
					$this->GenerateFixedForms($row[covpatient], $row[id]);
				}
			}
			//else
			//{
			//	// patient bills
			//	$query = "SELECT DISTINCT procpatient FROM procrec WHERE proccurcovtp='$bill_request_type'
			//				AND procbalcurrent>'0'";
			//	$result = $sql->query($query);
			//	if (!$sql->results($result)) 
			//	{
			//		DIE("No patients to be Billed");
			//	}
			//
			//	while($row = $sql->fetch_array($result))
			//	{	
			//		$this->GenerateFixedForms($row[procpatient], 0);
			//	}
			//}
	
			if (!empty($this->form_buffer))
			{
				$new_buffer = "";
				$recs = explode("\n",$this->form_buffer);
				$count = count($recs);
				if ($count == 0)
				{
					echo "Error getting buffer<BR>";
				}
				
				for ($i=0;$i<$count;$i++)
				{
					if (substr($recs[$i],0,3) == "XXX")
						continue;
					if (substr($recs[$i],0,1) == "")
						continue;
					if (substr($recs[$i],0,1) == " ")
						continue;
					$new_buffer .= $recs[$i];
					$new_buffer .= "\n";
				}
				$this->form_buffer = $new_buffer;
			}
			if ($this->pat_processed > 0)
			{
				
				$this->ShowBillsToMark();
			}
			else
			{
				echo "
				<P>
				<CENTER>
				<$STDFONT_B><B>"._("Nothing to Bill!")."</B><$STDFONT_E>
				</CENTER>
				<P>
				<CENTER>
				<A HREF=\"$this->page_name?$_auth&module=$module\"
				><$STDFONT_B>"._("Return to Fixed Forms Generation Menu")."<$STDFONT_E></A>
				</CENTER>
				<P>
				";
			}
			return;

		} // end geninsform

		if ($viewaction=="mark")
		{
			$this->MarkBilled();
			return;
		}
		DIE("Something is wrong in generateforms");

	}

	function MarkBilled()
	{

		reset ($GLOBALS);
		while (list($k,$v)=each($GLOBALS)) global $$k;

	   	if (count($processed)<1) 
		{
			echo "
		 	<P>
		 	<CENTER>
		  	<$STDFONT_B><B>"._("Nothing set to be marked!")."</B><$STDFONT_E>
		 	</CENTER>
		 	<P>
		 	<CENTER>
		  	<A HREF=\"$this->page_name?$_auth&module=$module\"
		  	><$STDFONT_B>"._("Return to Fixed Forms Generation Menu")."<$STDFONT_E></A>
		 	</CENTER>
		 	<P>
			";
			return;
       	} 
     	for ($i=0;$i<count($processed);$i++) 
		{
       		echo "
       		Marking ".$processed[$i]." ...<BR> 
       		";
       		// start of insert loop for billed legder entries
       		$query = "SELECT id,procbalcurrent,proccurcovid FROM procrec";
			$query .= " WHERE procpatient='$processed[$i]' AND proccurcovid='$proccovid[$i]'";
			$query .= " AND proccurcovtp='$billtype' AND procbilled='0' AND procbalcurrent>'0'";
       		$result = $sql->query($query);
       		if (!$result)
       		{
       			echo "Mark failed getting procrecs<BR>";
       			DIE("Mark failed getting procrecs");
       		}
       		while ($bill_tran = $sql->fetch_array($result))
       		{
       			$cur_bal = $bill_tran[procbalcurrent];
          		$proc_id = $bill_tran[id];
          		$cov_id  = $bill_tran[proccurcovid];
				$payreccat = BILLED;
				$query = $sql->insert_query("payrec",
					array (
						"payrecdtadd" => $cur_date,
						"payrecdtmod" => $cur_date,
						"payrecpatient" => $processed[$i],
						"payrecdt" => $cur_date,
						"payreccat" => $payreccat,
						"payrecproc" => $proc_id,
						"payreclink" => $cov_id,
						"payrecsource" => $billtype,
						"payrecamt" => $cur_bal,
						"payrecdescrip" => "Billed",
						"payreclock" => "unlocked"
						)	
					);

           		$pay_result = $sql->query ($query);
           		if ($pay_result)
               		echo "<$STDFONT_B>$Adding Bill Date to ledger.<$STDFONT_E><BR> \n";
           		else
               		echo "<$STDFONT_B>$Failed Adding Bill Date to ledger!!<$STDFONT_E><BR> \n";
       			$query = "UPDATE procrec SET procbilled = '1',procdtbilled = '".addslashes($cur_date)."'".
						 " WHERE id = '".$proc_id."'";
       			$proc_result = $sql->query ($query);
       			if ($result) 
				{ 
					echo _("done").".<BR>\n"; 
				}
       			else        
				{ 
					echo _("ERROR")."<BR>\n"; 
				}

       		}
     	} // end for processed
     	echo "
      	<P>
      	<CENTER>
       	<A HREF=\"$this->page_name?$_auth&module=$module\"
       	><$STDFONT_B>"._("Back")."<$STDFONT_E></A>
      	</CENTER>
      	<P>
     	";
	}

	function Insurer($procstack)
	{
		reset ($GLOBALS);
		while (list($k,$v)=each($GLOBALS)) global $$k;

		unset($GLOBALS[da0]);
		unset($GLOBALS[da1]);
		unset($GLOBALS[da2]);

		global $da0,$da1,$da2;

		$row = $procstack[0]; // all rows are the same
		$cov = $row[proccurcovid];
		$pat = $row[procpatient];

		$da0[recid1] = "DA0";
		$da1[recid] = "XXX";
		$da2[recid] = "XXX";

		$coverage = new Coverage($cov);
		if (!$coverage)
		{
			echo "Error Insurer no coverage<BR>";
			return;
		}

		$insco = $coverage->covinsco;
		if (!$insco)
		{
			echo "Error Insurer no insurance<BR>";
			return;
		}

		$patient = new Patient($pat);
		if (!$patient)
		{
			echo "Error Insurer no patient<BR>";
			return;
		}
			
		$da0[seqno1] = "01";
		$da0[patcntl1] = $ca0[patcntl];
		
		if ($bill_request_type == PRIMARY)
		{
			$da0[clmfileind1] = "P";
			$da0[clmsource1] = "F";
		}
		else
		{
			$da0[clmfileind1] = "I";
			$da0[clmsource1] = " ";
		}

		$da0[instypcd1] = $insco->modifiers[0];
		$da0[payerid1] = $this->CleanNumber($insco->local_record[inscoid]); // NAIC #
		$da0[payername1] = $this->CleanChar($insco->insconame);
		$da0[patgrpno1] = $this->CleanNumber($coverage->covpatgrpno);
		$da0[assign1] = "Y";
		$da0[patsigsrc1] = "C";

		if ($coverage->covreldep == "S")
			$da0[patrel1] = "01";
		if ($coverage->covreldep == "H" OR $coverage->covreldep == "W")
			$da0[patrel1] = "02";
		if ($coverage->covreldep == "C")
			$da0[patrel1] = "03";

		$da0[patidno1] = $this->CleanNumber($coverage->covpatinsno);

		if ($coverage->covdep == 0)  // patient is the insured
		{
			$da0[insrdlname1] = $this->CleanChar($patient->ptlname);
			$da0[insrdfname1] = $this->CleanChar($patient->ptfname);
			$da0[insrdmi1] = $this->CleanChar($patient->ptmname);
			$da0[insrdsex1] = $this->CleanChar($patient->ptsex);
			$da0[insrddob1] = $this->CleanNumber($patient->ptdob);
	
		}
		else
		{
			$guarantor = new Guarantor($coverage->covdep);
			if (!$guarantor)
			{
				echo "Error Insurer guarantor failed<BR>";
				return;
			}	
			$da0[insrdlname1] = $this->CleanChar($guarantor->guarlname);
			$da0[insrdfname1] = $this->CleanChar($guarantor->guarfname);
			$da0[insrdmi1] = $this->CleanChar($guarantor->guarmname);
			$da0[insrdsex1] = $this->CleanChar($guarantor->guarsex);
			$da0[insrddob1] = $this->CleanNumber($guarantor->guardob);

		}
		if ($row[procauth] != 0)
        {
            $auth_row = freemed_get_link_rec($row[procauth],"authorizations");
            if (!$auth_row)
                echo "Failed to read procauth";
			$auth_num = $auth_row[authnum];
            if (!$auth_num)
            {
                echo "Authorization number Invalid";
                $auth_num = "AUTHXXXX";
            }
            if (!date_in_range($cur_date,$auth_row[authdtbegin],$auth_row[authdtend]))
            {
                echo "Warning: Authorization $auth_num has expired";

            }
			$da0[authno1] = $this->CleanNumber($auth_num);
        }
        else
        {
            echo "Warning - No Authorization for this procedure<BR>";
        }

		
	} // end insurer

	function ClaimHeader($procstack)
	{
		reset ($GLOBALS);
		while (list($k,$v)=each($GLOBALS)) global $$k;
		
		unset($GLOBALS[ca0]);
		unset($GLOBALS[cb0]);
		global $ca0, $cb0;

		$row = $procstack[0]; // all rows are the same
		$cov = $row[proccurcovid];
		$pat = $row[procpatient];

		$ca0[recid] = "CA0";
		$cb0[recid] = "XXX";
		$patient = new Patient($pat);
		if (!$patient)
		{
			echo "Error in claimheader no patient<BR>";
			return;
		}

		$ca0[patcntl] = $patient->local_record[id];
		$ca0[patlname] = $this->CleanChar($patient->local_record[ptlname]);
		$ca0[patfname] = $this->CleanChar($patient->local_record[ptfname]);
		$ca0[patdob] = $this->CleanNumber($patient->local_record[ptdob]);
		$ca0[patsex] = $this->CleanChar($patient->local_record[ptsex]);
		$ca0[pataddr1] = $this->CleanChar($patient->local_record[ptaddr1]);
		$ca0[pataddr2] = $this->CleanChar($patient->local_record[ptaddr2]);
		$ca0[patcity] = $this->CleanChar($patient->local_record[ptcity]);
		$ca0[patstate] = $this->CleanChar($patient->local_record[ptstate]);
		$ca0[patzip] = $this->CleanNumber($patient->local_record[ptzip]);
		$ca0[patphone] = $this->CleanNumber($patient->local_record[ptphone]);

     	// marital status
        if ($patient->ptmarital == "single") 
     		$ca0[patmarital] = "S";
        if ($patient->ptmarital == "divorced") 
     		$ca0[patmarital] = "D";
        if ($patient->ptmarital == "seperated") 
     		$ca0[patmarital] = "S";
        if ($patient->ptmarital == "married") 
     		$ca0[patmarital] = "M";
        if ($patient->ptmarital == "widowed") 
     		$ca0[patmarital] = "W";

		if ($patient->local_record == 0)
		{
			$ca0[patstudent] = "N";
		}
		else
		{
			// look up the status record.
		}
		if ($patient->local_record[ptempl] == "y")
			$ca0[patempl] = "1";
		if ($patient->local_record[ptempl] == "p")
			$ca0[patempl] = "2";
		if ($patient->local_record[ptempl] == "n")
			$ca0[patempl] = "3";
		if ($patient->local_record[ptempl] == "s")
			$ca0[patempl] = "4";
		if ($patient->local_record[ptempl] == "r")
			$ca0[patempl] = "5";
		if ($patient->local_record[ptempl] == "m")
			$ca0[patempl] = "5";
		if ($patient->local_record[ptempl] == "u")
			$ca0[patempl] = "9";

		$coverage = new Coverage($cov);
		if (!$coverage)
		{
			echo "Error. patient coverage invalid<BR>";
			return;
		}
		if ($coverage->local_record[covtype] == PRIMARY)
		{
			if (fm_verify_patient_coverage($pat,SECONDARY))
				$ca0[othrins] = "1"; // yes has other
			else
				$ca0[othrins] = "3"; // no
			
		}
		else
		{
				$ca0[othrins] = "1"; // must have other
		}
		$ca0[clmeditind] = "F"; // comercial filing
		//$ca0[clmtype] = "Z";

		// need origin codes if payer is REG06
		

	} // end patient

	function ProviderHeader($procstack)
	{
		
		reset ($GLOBALS);
		while (list($k,$v)=each($GLOBALS)) global $$k;
		unset($GLOBALS[ba0]);
		unset($GLOBALS[ba1]);
		global $ba0, $ba1;

		$row = $procstack[0]; // all rows are the same
		$doc = $row[procphysician];
		$cov = $row[proccurcovid];

		$ba0[recid] = "BA0";
		$ba1[recid] = "BA1";

		$physician = new Physician($doc);
		if (!$physician)
		{
			echo "Error no physician<BR>";
			return;
		}
		$coverage = new Coverage($cov);
		if (!$coverage)
		{
			echo "Error no coverage<BR>";
			return;
		}
		$insco = $coverage->covinsco;
		if (!$insco)
		{
			echo "Error no insco<BR>";
			return;
		}
		
		
	
		$ba0[batchtype] = "100";  

		$ba0[batchnum] = "0001"; // needs incrementer
		$ba0[batchid] = "000001";  // only used once for 30 days!!!
		$ba0[taxid] = $this->CleanNumber($physician->local_record[physsn]);
		$ba0[idtype] = "S";

		// other id's are dependant on the ins NAIC no

		$ba0[prlname] = $this->CleanChar($physician->local_record[phylname]);
		$ba0[prfname] = $this->CleanChar($physician->local_record[phyfname]);
		$ba0[prspec]  = $this->CleanNumber($physician->local_record[physpe1]);

		$provider_id = "0";
        $grp = $insco->local_record[inscogroup];
        if (!$grp)
        {
            $name = $insco->local_record[insconame];
            echo "Failed getting inscogroup for $name<BR>";
        }

        $providerids = explode(":",$physician->local_record[phyidmap]);
        $provider_id = $providerids[$grp];

		$naic = $insco->inscoid;
		$naic = strtoupper($naic);

		if (fm_value_in_array($ins_upin,$naic))
		{
			$ba0[upin] = $this->CleanNumber($provider_id);
		}
		if (fm_value_in_array($ins_medicaid,$naic))
		{
			$ba0[mcid] = $$this->CleanNumber(provider_id);
		}
		if (fm_value_in_array($ins_commercial,$naic))
		{
			$ba0[ciid] = $this->CleanNumber($provider_id);
		}
	

		$ba1[batchtype] = $ba0[batchtype];
		$ba1[batchid] = $ba0[batchid];
		$ba1[batchnum] = $ba0[batchnum];
		$ba1[praddr1] = $this->CleanChar($this_physician->local_record[phyaddr1a]);
		$ba1[praddr2] = $this->CleanChar($this_physician->local_record[phyaddr2a]);
		$ba1[prcity] = $this->CleanChar($this_physician->local_record[phycitya]);
		$ba1[prstate] = $this->CleanChar($this_physician->local_record[phystatea]);
		$ba1[przip] = $this->CleanNumber($this_physician->local_record[phyzipa]);
		$ba1[prphone] = $this->CleanNumber($this_physician->local_record[phyphonea]);
		$ba1[prpayaddr1] = $this->CleanChar($this_physician->local_record[phyaddr1a]);
		$ba1[prpayaddr2] = $this->CleanChar($this_physician->local_record[phyaddr2a]);
		$ba1[prpaycity] = $this->CleanChar($this_physician->local_record[phycitya]);
		$ba1[prpaystate] = $this->CleanChar($this_physician->local_record[phystatea]);
		$ba1[prpayzip] = $this->CleanNumber($this_physician->local_record[phyzipa]);
		$ba1[prpayphone] = $this->CleanNumber($this_physician->local_record[phyphonea]);
		return;
		
	}  // end provider

	function ClaimData($procstack)
	{
		reset ($GLOBALS);
		while (list($k,$v)=each($GLOBALS)) global $$k;
		unset($GLOBALS[ea0]);
		unset($GLOBALS[ea1]);
		global $ea0,$ea1;
		
		$row = $procstack[0];

		$ea0[recid] = "EA0";
		$ea1[recid] = "XXX";

		$ea0[patcntl] = $ca0[patcntl];

		$eao[relemp] = "N";
		$ea0[accident] = "N";
		$ea0[symptomind] = "0";

		if ($row[proceoc] != 0)
        {
            $eoc_row = freemed_get_link_rec($row[proceoc], "eoc");
            if (!$eoc_row)
                echo "Failed reading eoc record<BR>";

            if ($eoc_row[eocrelauto] == "yes")
            {
				$ea0[accident] = "A";
				$ea0[symptomind] = "0";
                $accident_date = $eoc_row[eocstartdate];
            }
            if ($eoc_row[eocrelemp] == "yes")
            {
				$ea0[relemp] = "Y";
                $accident_date = $eoc_row[eocstartdate];
            }
            if ($eoc_row[eocrelother] == "yes")  // other Accident!
            {
				$ea0[accident] = "O";
				$ea0[symptomind] = "0";
                $accident_date = $eoc_row[eocstartdate];
            }
            if ($eoc_row[eocrelpreg] == "yes")  // preg?
            {
				$ea0[symptomind] = "2";
                $accident_date = $eoc_row[eocpreglastper];
            }

        }
        else
        {
            echo "Warning - No EOC for this procedure $row[procdt]<BR>";
        }

		if ($ea0[accident] == "A" OR 
			$ea0[accident] == "O" OR 
			$ea0[symtomind] == "1" OR
			$ea0[symtomind] == "2")
		{
			$ea0[accsympdt] = $this->CleanNumber($accident_date);
		}
		else
			$ea0[accsympdt] = " ";

		$count = count($procstack);
        if ($count == 0)
        {
            echo "Error in GenClaimSegment Stack count 0<BR>";
            return;
        }

        $diagset = new diagnosisSet();
        for ($i=0;$i<$count;$i++)
        {
            $row = $procstack[$i];

            // this should never overflow if the control break is working.
            $diagset->testAddSet($row[procdiag1],
                             $row[procdiag2],
                             $row[procdiag3],
                             $row[procdiag4]);

        }

		$diagstack = $diagset->getStack();
        $diagcnt = count($diagstack);

        if ($diagcnt == 0)
        {
            echo "Procedures do not have Diagnosis codes<BR>";
            return;
        }


        for ($i=1;$diagcnt>=$i;$i++)
        {
			$var = "diag".$i;
            $ea0[$var] = $this->CleanNumber($diagstack[$i]);
            //$this->edi_buffer .= $icd9code;


        }

		
		return;
	}
	function ServiceDetail($procstack)
	{
		reset ($GLOBALS);
		while (list($k,$v)=each($GLOBALS)) global $$k;
		unset ($GLOBALS[fa0]);
		global $fa0;

		$row = $procstack[0];

		$diagset = new diagnosisSet();

		$pos = 0;
		$fac_row=0;
		$fac_row = freemed_get_link_rec($row[procpos], "facility");
        if ($fac_row)
        {
            // use code from facility
            if ($fac_row[psrpos] == 0)
            {
                echo "Facility does not have a pos code<BR>";
            }
            $cur_pos = freemed_get_link_rec($fac_row[psrpos], "pos");
            if (!$cur_pos)
                echo "Failed reading pos table";
            $pos = $cur_pos[posname];

        }
        if ($pos == 0)
        {
            // plug with Office code
            $pos="11";
            echo "Warning: Plugged pos with Office Code 11";
        }
		
		$count = count($procstack);

		if ($count == 0)
		{
			echo "Error no procedures in Service<BR?";
			return;
		}
		for ($i=0;$i<$count;$i++)
		{
			$off = $i + 1;
			$row = $procstack[$i];
			$seq = $off;
			if ($seq < 10)
				$seq = "0".$seq;

			$fa0[recid.($off)] = "FA0";
			$fa0[seqno.($off)] = $seq;
			$fa0[patcntl.($off)] = $ca0[patcntl];
			$fa0[startdt.($off)] = $this->CleanNumber($row[procdt]);
			$fa0[enddt.($off)] = $this->CleanNumber($row[procdt]);
			$fa0[pos.($off)]   = $pos;

			$cur_cpt = freemed_get_link_rec ($row[proccpt], "cpt");
            if (!$cur_cpt)
                echo "Failed reading cpt table<BR>";
            $cur_insco = $insco->local_record[id];
            $tos_stack = fm_split_into_array ($cur_cpt[cpttos]);
            $tosid = ( ($tos_stack[$cur_insco] < 1) ?
                      $cur_cpt[cptdeftos] :
                      $tos_stack[$cur_insco] );
			if ($tosid == 0)
            {
                echo "No default type of service for this proc $row[procdt]<BR>";
                $tos = "TOSXXXX";
            }
            else
            {
                $cur_tos = freemed_get_link_rec($tosid, "tos");
                if (!$cur_tos)
                    $this->Error("Failed reading tos table");
                $tos = $cur_tos[tosname];
            }

			$fa0[tos.($off)] = $tos;
		
			$cur_cpt = freemed_get_link_rec ($row[proccpt], "cpt");
            if (!$cur_cpt)
                $this->Error("Failed reading cpt table");

            $diagset->testAddSet($row[procdiag1],
                             $row[procdiag2],
                             $row[procdiag3],
                             $row[procdiag4]);

            $diag_xref = $diagset->xrefList($row[procdiag1],
                             $row[procdiag2],
                             $row[procdiag3],
                             $row[procdiag4]);

			$fa0[cpt.($off)] = $cur_cpt[cptcode];
			$fa0[charges.($off)] = floor($row[procbalcurrent]);

            $diag_xref = explode(",",$diag_xref);
			for ($x=0;$x<count($diag_xref);$x++)
			{
				echo "xref $diag_xref[$x]<BR>";
				$xoff = $x+1;
				$var = "diag".$xoff.$off;
				$fa0[$var] = $diag_xref[$x];
			}
			$fa0[units.($off)] = floor($row[procunits]);


		}

		//$fa0
		return;
	}

	function ClaimTrailer($procstack)
	{
		reset ($GLOBALS);
		while (list($k,$v)=each($GLOBALS)) global $$k;
		unset($GLOBALS[xa0]);
		global $xa0;

		$xa0[recid] = "XA0";

		$count = count($procstack);

		if ($count == 0)
		{
			echo "Error no procedures in Service<BR?";
			return;
		}

		$total = 0;

		for ($i=0;$i<$count;$i++)
		{
			$row = $procstack[$i];
			$total += $row[procbalcurrent];
			
		}
		$xa0[totalcharge] = $total;
		
		
		return;
	}

	function ProviderTrailer($procstack)
	{
		reset ($GLOBALS);
		while (list($k,$v)=each($GLOBALS)) global $$k;
		reset ($this->rendorform_variables);
		while (list($k,$v)=each($this->rendorform_variables)) global $$v;
		return;
	}

	function ProcessClaims($procstack)
	{

		$this->ProviderHeader($procstack); // batch header	
		$this->ClaimHeader($procstack); // claim headers
		$this->Insurer($procstack);    // insurance records 
		$this->ClaimData($procstack);    // claim data EA0-EA1
		$this->ServiceDetail($procstack);    // service FA0-FB0-FB1
		$this->ClaimTrailer($procstack);    // clm trlr XA0
		$this->ClaimTrailer($procstack);    // clm trlr XA0
		$this->ProviderTrailer($procstack); // batch trailer
	}

	function CleanChar($data)
    {
            $data = stripslashes($data);
            $data = str_replace("'"," ",$data);
            $data = str_replace("-"," ",$data);
            $data = str_replace(";"," ",$data);
            $data = str_replace("(","",$data);
            $data = str_replace(")","",$data);
            $data = str_replace(":"," ",$data);
            $data = str_replace("."," ",$data);
            $data = str_replace(","," ",$data);
            $data = trim($data);
            $data = strtoupper($data);
            return $data;
    } // end cleanchar

    function CleanNumber($data)
    {
            $data = $this->CleanChar($data);
            $data = str_replace(" ","",$data);
            $data = trim($data);
            return $data;
    } // end cleannumber

	function GenerateFixedForms($parmpatient, $parmcovid)
	{
		
		reset ($GLOBALS);
		while (list($k,$v)=each($GLOBALS)) global $$k;
		reset ($this->rendorform_variables);
		while (list($k,$v)=each($this->rendorform_variables)) global $$v;


	    // zero the buffer and counter
	    $buffer = "";
	    $counter = 0;
	    $current_patient = 0;

	    // get list of all patient who need to be billed
	    $query = "SELECT procpatient from procrec
							  WHERE (proccurcovtp = '$bill_request_type' AND
									 proccurcovid = '$parmcovid' AND
									 procbalcurrent > '0' AND
									 procpatient = '$parmpatient' AND
                                     procbillable = '0' AND
                                     procbilled = '0')";
  
   		 $b_result = $sql->query($query);
                          

		 if (!$sql->results($b_result)) 
		 {
			return false;
	   	 } // if there is no result, end


        // grab form information form
        $this_form = freemed_get_link_rec ($whichform, "fixedform");




     //
     // grab all the procedure for this patient
     //
	    $query = "SELECT * FROM procrec
							  WHERE (proccurcovtp = '$bill_request_type' AND
									 proccurcovid = '$parmcovid' AND
									 procbalcurrent > '0' AND
									 procpatient = '$parmpatient' AND
                                     procbillable = '0' AND
                                     procbilled = '0') 
                           	  ORDER BY procpos,procphysician,proceoc,procauth,procdt";
     	$result = $sql->query ($query);

     	if (!$result or ($result==0))
       		DIE ("Failed getting procedures");

     	$first_procedure = 0;
		$proccount=0;
     	while ($r = $sql->fetch_array ($result)) 
		{
       		if ($first_procedure == 0)
       		{
		   		$prev_pos = $r["procpos"];
		   		$prev_doc = $r["procphysician"];
	   	   		$prev_auth = $r["procauth"];
           		$prev_eoc = $r["proceoc"];
           		$prev_key = $prev_pos.$prev_doc.$prev_eoc.$prev_auth;
				$diagset = new diagnosisSet();
           		$first_procedure = 1;
       		}
	
	   		$cur_pos = $r["procpos"];
	   		$cur_doc = $r["procphysician"];
       		$cur_auth = $r["procauth"];
       		$cur_eoc = $r["proceoc"];
       		$cur_key = $cur_pos.$cur_doc.$cur_eoc.$cur_auth;

			echo "physician $r[procphysician]<BR>";

       		$render_form = true; // reset to render form

       		if (!($diagset->testAddSet ($r[procdiag1], $r[procdiag2],
                                    $r[procdiag3], $r[procdiag4])) OR
            	($proccount > $this_form[ffloopnum]         )  OR
            	($prev_key != $cur_key) )
       		{
         		if ($prev_key != $cur_key)
				{
              		$prev_key = $cur_key;
					echo "keychange $r[procphysician]<BR>";
				}

         		// drop the current form to the buffer
		 		$this->ProcessClaims($procstack); // batch trailer
         		$this->form_buffer .= render_fixedForm ($whichform);
				echo "Render<BR>";

         		// reset the diag_set array
         		unset ($diagset);
		 		unset ($procstack);
				reset ($this->rendorform_variables);
				while (list($k,$v)=each($this->rendorform_variables)) 
				{
					unset($$v);
					echo "$$v<BR>";
				}
				$proccount=0;
         		$diagset = new diagnosisSet ();
         		$test_AddSet = $diagset->testAddSet ($r[procdiag1], 
								$r[procdiag2], 
								$r[procdiag3], 
								$r[procdiag4]);
         		if (!$test_AddSet)
           			 DIE("AddSet failed!!");

       		} 

			$procstack[$proccount] = $r;
			$proccount++;



     	} // end of looping for all charges


	 	if ($proccount > 0)
		{
        	// drop the current form to the buffer
			reset ($this->rendorform_variables);
			//while (list($k,$v)=each($this->rendorform_variables)) 
			//	unset($$v);
		 	$this->ProcessClaims($procstack); // batch trailer
         	$this->form_buffer .= render_fixedForm ($whichform);
			echo "Render<BR>";
		}
		

     	$this->pat_processed++;
     	$this->patient_forms[$this->pat_processed] = $parmpatient;
     	$this->patient_cov[$this->pat_processed] = $parmcovid;


   } // end generateFixed

   function ShowBillsToMark()
   {
		reset ($GLOBALS);
		while (list($k,$v)=each($GLOBALS)) global $$k;
		reset ($this->rendorform_variables);
		while (list($k,$v)=each($this->rendorform_variables)) global $$v;
   #################### TAKE THIS OUT AFTER TESTING #######################
   #echo "<PRE>\n".prepare($form_buffer)."\n</PRE>\n";
   ########################################################################

   echo "
    <FORM ACTION=\"echo.php/form.txt\" METHOD=POST>
     <CENTER>
      <$STDFONT_B><B>"._("Preview")."</B><$STDFONT_E>
     </CENTER>
     <BR>
     <TEXTAREA NAME=\"text\" ROWS=10 COLS=81
     >".prepare($this->form_buffer)."</TEXTAREA>
    <P>
    <CENTER>
     <SELECT NAME=\"type\">
      <OPTION VALUE=\"\">"._("Render to Screen")."
      <OPTION VALUE=\"application/x-rendered-text\">Render to File
     </SELECT>
     <INPUT TYPE=SUBMIT VALUE=\""._("Get HCFA Rendered Text File")."\">
    </CENTER>
    </FORM>
    <P>
   ";

   // present the form so that we can mark as billed
   echo "
    <CENTER>
    <$STDFONT_B><B>"._("Mark as Billed")."</B><$STDFONT_E>
    </CENTER>
    <BR>
    <FORM ACTION=\"$this->page_name\" METHOD=POST>
     <INPUT TYPE=HIDDEN NAME=\"_auth\"  VALUE=\"$_auth\">
     <INPUT TYPE=HIDDEN NAME=\"action\" VALUE=\"addform\">
     <INPUT TYPE=HIDDEN NAME=\"viewaction\" VALUE=\"mark\">
     <INPUT TYPE=HIDDEN NAME=\"module\" VALUE=\"$module\">
     <INPUT TYPE=HIDDEN NAME=\"been_here\" VALUE=\"$been_here\">
     <INPUT TYPE=HIDDEN NAME=\"billtype\" VALUE=\"$bill_request_type\">
   ";
   for ($i=1;$i<=$this->pat_processed;$i++) {
     $this_patient = new Patient ($this->patient_forms[$i]);
     echo "
       <INPUT TYPE=CHECKBOX NAME=\"processed$brackets\" 
        VALUE=\"".$this->patient_forms[$i]."\" CHECKED>
       <INPUT TYPE=HIDDEN NAME=\"proccovid$brackets\" 
        VALUE=\"".$this->patient_cov[$i]."\" CHECKED>
       ".$this_patient->fullName(false)."
       (<A HREF=\"manage.php?$_auth&id=$patient_forms[$i]\"
        >".$this_patient->local_record["ptid"]."</A>) <BR>
     ";
   } // end looping for all processed patients
   echo "
    <P>
    <INPUT TYPE=SUBMIT VALUE=\""._("Mark as Billed")."\">
    </FORM>
    <P>
   ";

		
	} // end ShowMarkBilled

	function view()
	{
		reset ($GLOBALS);
		while (list($k,$v)=each($GLOBALS)) global $$k;
	
	    echo "
		<TABLE BORDER=0 CELLSPACING=0 CELLPADDING=3
		 VALIGN=MIDDLE ALIGN=CENTER>
		<TR>
		 <TD COLSPAN=2>
		  <CENTER>
		   <$STDFONT_B><B>"._("Generate Insurance Claim Forms")."</B><$STDFONT_E>
		  </CENTER>
		 </TD>
    	</TR>

		<FORM ACTION=\"$this->page_name\" METHOD=POST>
		<INPUT TYPE=HIDDEN NAME=\"_auth\"  VALUE=\"".prepare($_auth)."\">
		<INPUT TYPE=HIDDEN NAME=\"action\" VALUE=\"addform\">
		<INPUT TYPE=HIDDEN NAME=\"viewaction\" VALUE=\"geninsform\">
		<INPUT TYPE=HIDDEN NAME=\"module\" VALUE=\"$module\">

		<TR>
		 <TD ALIGN=RIGHT>
		  <CENTER>
		   <$STDFONT_B>Claim Form : <$STDFONT_E>
		  </CENTER>
		 </TD>
     	<TD ALIGN=LEFT>
      	<SELECT NAME=\"whichform\">
   		";
	   $result = $sql->query ("SELECT * FROM fixedform WHERE id='3'");
							 //ORDER BY ffname, ffdescrip");
	   while ($r = $sql->fetch_array ($result)) {
		echo "
		 <OPTION VALUE=\"$r[id]\">".prepare($r[ffname])."
		";
	   } // end looping through results                         

	   echo "
		 </TD>
		</TR>
		<TR>
		   <TD ALIGN=RIGHT>
			<$STDFONT_B>To : <$STDFONT_E>
		   </TD><TD ALIGN=LEFT>
			<SELECT NAME=\"bill_request_type\">
			 <OPTION VALUE=\"1\">"._("1st Insurance")."
			 <OPTION VALUE=\"2\">"._("2nd Insurance")."
        </SELECT>
		<INPUT TYPE=HIDDEN NAME=\"been_here\" VALUE=\"1\">
       </TD>
    	</TR>

		<TR>
		 <TD COLSPAN=2>
		  <CENTER>
		   <INPUT TYPE=SUBMIT VALUE=\""._("Go")."\">
		  </CENTER>
		 </TD>
		</TR>

		</FORM>

		</TABLE>
	   ";
	} // end view functions
	

} // end class MedicareMCSIFormsModule

register_module("MedicareMCSIFormsModule");

} // end if not defined

?>
