<?php
require ("../../core/connectDB.php");
require ("../PHP_functions2.php");

Class D112 extends PHP_functions{
	private $versiune = "";
	private $d_rec = "";
	private $tip_rec = "";
	private $anul = "";
	private $luna = "";

	private $XDP_name = "";

	private $arr_angajator = array();
	private $arr_semnatar = array();
	private $arr_asigurati = array();

	private $str_header = "";
  	private $str_footer = "";

  	public function __construct(){
  		$this->XDP_name = "D112_".$_SESSION['cif_dsoft']."_".$_POST['anul']."_".$_POST['luna'];
		$this->versiune = $_POST['versiune'];

		$this->luna = $_POST['luna'];
		if($_POST['luna'] < 10){$this->luna = "0".$_POST['luna'];}
		$this->anul = $_POST['anul'];
		$this->tip_rec = $_POST['tip_rec'];
		$this->d_rec = $_POST['d_rec'];

		self::set_header();
		self::set_footer();

		self::get_date_firma();
		self::get_date_semnatar();
		self::get_date_asigurati();

		
  	}

  	private function set_header(){
		if($this->versiune == "V5"){
			$this->str_header = 
			'<?xml version="1.0" encoding="UTF-8"?>
			<?xfa generator="XFA2_4" APIVersion="3.3.10270.0"?>
			<xdp:xdp xmlns:xdp="http://ns.adobe.com/xdp/" timeStamp="2011-01-26T15:02:10Z" uuid="3f9747ce-1b0e-4b12-b56f-200c584a07bf">
			<xfa:datasets  xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/">
			<xfa:data>
			<frmMAIN>';
		}
		else if ($this->versiune == "V6"){
			$this->str_header = 
			'<?xml version="1.0" encoding="UTF-8"?>
			<?xfa generator="XFA2_4" APIVersion="3.6.21022.0"?>
			<xdp:xdp xmlns:xdp="http://ns.adobe.com/xdp/" timeStamp="2021-05-28T07:09:38Z" uuid="22324997-1d6f-4f3e-8f7a-14bb1e49041d">
			<xfa:datasets xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/">
			<xfa:data>
			<frmMAIN>';
		}
	}
	private function set_footer(){
		// $this->str_footer = '</xfa:data>
		// 					</xfa:datasets>
		// 					<pdf href="'.$this->file_name.'.pdf" xmlns="http://ns.adobe.com/xdp/pdf/"/>
		// 					</xdp:xdp>';

		$b64Doc = chunk_split(base64_encode(file_get_contents("../../xml/declaratii/D112/"."D112_".$this->versiune.".pdf")));
		$this->str_footer = '</frmMAIN>
							</xfa:data>
							</xfa:datasets>
							<pdf xmlns="http://ns.adobe.com/xdp/pdf/">
								<document>
						     		<chunk>'.$b64Doc.'</chunk>
						    	</document>
						 	</pdf>
						 	</xdp:xdp>';
	}

	private function get_date_firma(){
		$sql_cmd = "SELECT tip, valoare FROM date_generale 
					WHERE cod='DATE_FIRMA'";
		$arr = self::sql_exec($sql_cmd, "return");
		for($i = 0; $i < sizeof($arr); $i++){			
			$this->arr_angajator[$arr[$i]['tip']] = $arr[$i]['valoare'];
		}
		$this->arr_angajator['judet_s_info'] = self::sql_exec("SELECT value FROM dsoft_global.rec_general 
																		   WHERE tip='JUDET' AND value2='".$this->arr_angajator['judet_s']."'", "1");
	}
	private function get_date_semnatar(){
		$temp = self::sql_exec("SELECT val1 AS nume, val2 AS prenume, val3 AS functie 
											  FROM config1 
											  WHERE den='SEMNATURI' AND tip='DECL'", "return");
		if($temp != ""){
			$this->arr_semnatar["nume"] = $temp[0]['nume']; 
			$this->arr_semnatar["prenume"] = $temp[0]['prenume']; 
			$this->arr_semnatar["functie"] = $temp[0]['functie']; 
		}
	}
	private function get_date_asigurati(){
		$_POST['tip'] = "SALARII";
		$_POST['subtip'] = "";
		$_POST['G_ANUL'] = $_POST['anul'];
		if($_POST['luna'] < 10 ){$_POST['luna'] = "0".$_POST['luna'];}
		$_POST['G_LUNA'] = $_POST['luna'];
		$_POST['de_la'] = $_POST['pana_la'] = $_POST['G_ANUL'].$_POST['G_LUNA'];

		$data_filter = self::data_filter();
		$sql_cmd = PHP_EOL .call_user_func("self::getSQL_SALARII");
		$sql_cmd .= self::test_where($sql_cmd, $data_filter);

		$sql_cmd = "SELECT codpers, nume, prenume, marca_c, 'A' AS sectiune, 
						IF(pensionar='N', 0, 1) AS pensionar,
						CASE norma_info_a
								WHEN '8' THEN 'N'
								WHEN '1' THEN 'P1'
								WHEN '2' THEN 'P2'
								WHEN '3' THEN 'P3'
								WHEN '4' THEN 'P4'
								WHEN '5' THEN 'P5'
								WHEN '6' THEN 'P6'
								WHEN '7' THEN 'P7'
					   	END tip_norma, norma_info_a, (ore_reg+ore_acd) AS ore_lucrate, (zi_cfp*ore_zi) AS ore_suspendate,  
					   	((ore_acd+ore_reg-(zi_cfp*ore_zi))/ore_zi) AS tot_zile_lucrate, b_som_s,
					   	ROUND(s_reg+s_acd+s_sup1+s_sup2+s_sup3+s_srb+s_npt+s_nel+s_inv+s_cm+s_co+s_con+s_prv+suma_bruta+t_spor+t_brut+suma_taxe+suma_ntaxe, 0) AS total_brut, (tich_M+tich_I+tich_X) AS val_tichete
					FROM (" .$sql_cmd .PHP_EOL;
		$sql_cmd .= ") d  ".self::get_gridORDER($_POST['tip']);

		$this->arr_asigurati = self::sql_exec($sql_cmd, "return");
	
		if ($this->arr_asigurati != ""){
			$arr_marca = array();
			$i = 0;
			$len = sizeof($this->arr_asigurati);
			for ($i; $i < $len; $i++){
				// $data_angaj = "";
				// $data_plec = "";

				// $data_plec = max($arr[$i]['dataAng'], $data_plec);
				// $data_angaj = max($arr[$i]['dataSf']+1, $data_angaj);

				// if ($data_angaj != "" && $data_angaj > $last){ $data_angaj = "";}

				// if ($data_plec > $last){ $data_plec = "";}

				// if ($data_angaj >= $data_plec){ $data_plec = "";}

				// if ($data_angaj != ""){
				// 	$arr[$i]['data_angaj'] = $data_angaj;
				// }
				// $arr[$i]['data_plec'] = $data_plec;

				// $this->arr_asigurati[$i]['sectiune'] = "A";
				// unset($arr[$i]['data_angaj']);
				// unset($arr[$i]['data_plec']);

				if (!in_array($this->arr_asigurati[$i]['marca_c'], $arr_marca)){
					$temp_intretinuti = self::get_date_intretinuti($this->arr_asigurati[$i]['marca_c']);
					if (!empty($temp_intretinuti)){
						$this->arr_intretinuti[$this->arr_asigurati[$i]['marca_c']] = $temp_intretinuti;
					}

					$this->arr_asigurati[$i]['MEDICALE'] = array();
					$temp_medicale = self::get_date_medicale($this->arr_asigurati[$i]['codpers']);
					$this->arr_asigurati[$i]['MEDICALE'] = $temp_medicale;	

					// print_r($temp_medicale);

					$temp_date_A = array();
					$temp_date_C = array();

					// if(stripos($this->arr_asigurati[$i]['sectiune'], "A") !== false){
					// 	$temp_date_A = self::set_date_A($this->arr_asigurati[$i]['marca_c']);
					// }


					if(stripos($this->arr_asigurati[$i]['sectiune'], "C") !== false){
						$temp_date_C = self::set_date_C($this->arr_asigurati[$i]['marca_c']);
					}


					if (!empty($temp_date_A)){
						foreach ($temp_date_A[0] AS $k=>$v){
							$this->arr_asigurati[$i][$k] = $v;
						}
					}
					array_push($arr_marca, $this->arr_asigurati[$i]['marca_c']);
				}
			}
		}
		print_r($this->arr_asigurati);
		// print_r($this->arr_intretinuti);
	}
	private function get_date_intretinuti($marca_c){
		$arr = array();
		if ($marca_c != ""){
			$sql_cmd = "SELECT marca_c, codpers_intr, nume_intr, prenume_intr, data_in, data_out, calitate
					FROM intretinuti
					WHERE marca_c='".$marca_c."' AND (calitate='S' OR calitate='A') ORDER BY calitate";

			$arr = self::sql_exec($sql_cmd, "return");
		}
		return $arr;
	}
	private function get_date_medicale($codpers){
		$arr = array();
		$sql_cmd = PHP_EOL .call_user_func("self::getSQL_MEDICALE");
		$sql_cmd = "SELECT * FROM (".$sql_cmd.") d WHERE codpers='".$codpers."'";
		$arr = self::sql_exec($sql_cmd, "return");
		return $arr;
	}



	private function set_date_A($marca_c){
		$sql_cmd = "SELECT 	'1' AS A_1,
							pensionar AS A_2,
							CASE norma
								WHEN '8' THEN 'N'
								WHEN '1' THEN 'P1'
								WHEN '2' THEN 'P2'
								WHEN '3' THEN 'P3'
								WHEN '4' THEN 'P4'
								WHEN '5' THEN 'P5'
								WHEN '6' THEN 'P6'
								WHEN '7' THEN 'P7'
						   	END A_3,
							norma AS A_4,
							b_gar_a AS A_5,
						   	(ore_acd+ore_reg) AS A_6,
						   	zi_cfp AS A_7,
						   	((ore_acd+ore_reg)/ore_zi) AS A_8,
						   	b_som_s AS A_9,
						   	b_san_s AS A_11,
						   	s_san_s AS A_12,
						   	b_cas_s AS A_13,
						   	s_cas_s AS A_14
					FROM salarii s
					LEFT JOIN contracte c ON (s.marca_c=c.marca_c AND c.id_contr=0 AND ((CONCAT( :G_ANUL , :G_LUNA , '01') BETWEEN data_angaj AND data_plec) OR data_plec=''))
					WHERE s.marca_c=".$marca_c." AND s.anul= :G_ANUL AND s.luna= :G_LUNA ";
		return self::sql_exec($sql_cmd, "return");
	}
	private function set_date_C($marca_c){
		$arr = array();
		$sql_cmd = "";
		$arr = self::sql_exec($sql_cmd, "return");
		return $arr;
	}

	private function set_date_angajator(){
		$str = 
		'<sbfrmAntetAng xfa:dataNode="dataGroup"/>
		<sbfrmPage1Ang>
			<sfmIdentif>
				<luna_r>'.$this->luna.'</luna_r>
				<an_r>'.$this->anul.'</an_r>
				<den>'.(isset($this->arr_angajator["denumire"])?$this->arr_angajator["denumire"]:"").'</den>
				<adrFisc>'.(isset($this->arr_angajator["adresa_f"])?$this->arr_angajator["adresa_f"]:"").'</adrFisc>
				<telFisc>'.(isset($this->arr_angajator["telefon"])?$this->arr_angajator["telefon"]:"").'</telFisc>
				<faxFisc>'.(isset($this->arr_angajator["fax"])?$this->arr_angajator["fax"]:"").'</faxFisc>
				<mailFisc>'.(isset($this->arr_angajator["email"])?$this->arr_angajator["email"]:"").'</mailFisc>
				<tRisc>0.000</tRisc>
				<caen>'.(isset($this->arr_angajator["caen"])?$this->arr_angajator["caen"]:"").'</caen>
				<cif>'.(isset($this->arr_angajator["cif"])?$this->arr_angajator["cif"]:"").'</cif>
				<Bifa_FdGar>1</Bifa_FdGar>
				<datCAM>1</datCAM>
				<Bifa_UM>0</Bifa_UM>
				<d_rec>'.$this->d_rec.'</d_rec>
				<tip_rec>'.$this->tip_rec.'</tip_rec>
				<art90>0</art90>
				<cifS/>
				<RO/>
				<data1/>
				<data2/>
			</sfmIdentif>
			<calcule1/>
			<Salt xfa:dataNode="dataGroup"/>
			<sfmSectAEtich xfa:dataNode="dataGroup"/>
			<sfmSectAVal>
				<nrcrt>1</nrcrt>
				<A_codOblig/>
				<codbuget/>
				<a_datorat>0</a_datorat>
				<a_deductibil>0</a_deductibil>
				<a_scutit>0</a_scutit>
				<a_plata>0</a_plata>
			</sfmSectAVal>
			<sfmSectATotal>
				<totalPlata_A>0</totalPlata_A>
			</sfmSectATotal>
			<sbfrmPrezenta>
				<Salt1 xfa:dataNode="dataGroup"/>
			</sbfrmPrezenta>
			<sbfrMesajFooter xfa:dataNode="dataGroup"/>
			<sbfrmFooter>
				<nume_declar>'.(isset($this->arr_semnatar["nume"])?$this->arr_semnatar["nume"]:"").'</nume_declar>
				<functie_declar>'.(isset($this->arr_semnatar["functie"])?$this->arr_semnatar["functie"]:"").'</functie_declar>
				<Nr_inreg/>
				<Data_inreg/>
				<prenume_declar>'.(isset($this->arr_semnatar["prenume"])?$this->arr_semnatar["prenume"]:"").'</prenume_declar>
			</sbfrmFooter>
			<sfmAnexa12 xfa:dataNode="dataGroup"/>
			<sfmIdentif2>
				<rgCom>'.(isset($this->arr_angajator["reg_com"])?$this->arr_angajator["reg_com"]:"").'</rgCom>
				<adrSoc>'.(isset($this->arr_angajator["adresa"])?$this->arr_angajator["adresa"]:"").'</adrSoc>
				<telSoc>'.(isset($this->arr_angajator["telefon"])?$this->arr_angajator["telefon"]:"").'</telSoc>
				<faxSoc>'.(isset($this->arr_angajator["fax"])?$this->arr_angajator["fax"]:"").'</faxSoc>
				<mailSoc>'.(isset($this->arr_angajator["email"])?$this->arr_angajator["email"]:"").'</mailSoc>
				<casaAng>'.(isset($this->arr_angajator["judet_s_info"])?$this->arr_angajator["judet_s_info"]:"").'</casaAng>
				<tRisc>0.000</tRisc>
				<datCAM>1</datCAM>
				<caenC/>
				<caen/>
				<cifAf01/>
				<cifAf02/>
				<pond01/>
				<cifAf1/>
				<cifAf2/>
				<pond1/>
				<bifa_CAM>0</bifa_CAM>
			</sfmIdentif2>
			<sfmSectB>
				<B_cnp/>
				<B_sanatate/>
				<B_pensie/>
				<B1_brut_salarii/>
				<B_sal/>
				<T1/>
				<T2/>
				<T3/>
				<T4/>
			</sfmSectB>
			<sbfrmSectiuneaC>
				<sfmSectC1>
					<c1_11/>
					<c1_21/>
					<c1_31/>
					<c1_T1/>
					<c1_12/>
					<c1_22/>
					<c1_32/>
					<c1_T2/>
					<c1_5/>
					<c1_7/>
					<c1_T3/>
					<c1_T/>
					<c1_33/>
					<c1_23/>
					<c1_13/>
					<c1_21_scutit/>
					<c1_31_scutit/>
					<c1_22_scutit/>
					<c1_32_scutit/>
				</sfmSectC1>
				<sfmSectC2>
					<c2_11/>
					<c2_12/>
					<c2_13/>
					<c2_14/>
					<c2_24/>
					<c2_22/>
					<c2_21/>
					<c2_15/>
					<c2_16/>
					<c2_26/>
					<c2_10/>
					<c2_140/>
					<c2_25/>
					<c2_23/>
					<c2_T6/>
					<c2_56/>
					<c2_54/>
					<c2_52/>
					<c2_51/>
					<c2_111/>
					<c2_112/>
					<c2_113/>
					<c2_114/>
					<c2_115/>
					<c2_116/>
					<c2_36/>
					<c2_34/>
					<c2_32/>
					<c2_31/>
					<c2_46/>
					<c2_44/>
					<c2_42/>
					<c2_41/>
					<c2_211/>
					<c2_212/>
					<c2_213/>
					<c2_214/>
					<c2_215/>
					<c2_216/>
				</sfmSectC2>
				<sbfrmC345>
					<c3_Suma>0</c3_Suma>
					<c3_Total>0</c3_Total>
					<c3_44/>
					<c3_43/>
					<c3_42/>
					<c3_41/>
					<c3_24/>
					<c3_23/>
					<c3_22/>
					<c3_21/>
					<c3_11/>
					<c3_12/>
					<c3_13/>
					<c3_14/>
					<Aj_nr/>
					<Aj_suma/>
					<c3_33/>
					<c3_34/>
					<c3_32/>
					<c3_31/>
					<C4_baza/>
					<C4_ct/>
					<C5_baza/>
					<C5_ct/>
					<CAM_constr/>
				</sbfrmC345>
			</sbfrmSectiuneaC>
			<sbfrmSectiuneaD>
				<D2/>
				<D3/>
			</sbfrmSectiuneaD>
			<sbfrmSectiuneaE>
				<E1_venit/>
				<E1_baza/>
				<E2_11/>
				<E2_12/>
				<E2_14/>
				<E2_16/>
				<E2_21/>
				<E2_22/>
				<E2_24/>
				<E2_26/>
				<E2_41/>
				<E2_42/>
				<E2_44/>
				<E2_46/>
				<E2_51/>
				<E2_52/>
				<E2_54/>
				<E2_56/>
				<E2_66>0</E2_66>
				<E3_11/>
				<E3_21/>
				<E3_31/>
				<E3_41/>
				<E3_12/>
				<E3_22/>
				<E3_32/>
				<E3_42/>
				<E3_13/>
				<E3_23/>
				<E3_33/>
				<E3_43/>
				<E3_total>0</E3_total>
				<E3_14/>
				<E3_24/>
				<E3_34/>
				<E3_44/>
				<E3_suma>0</E3_suma>
				<E2_140/>
				<E_Aj_nr/>
				<E_Aj_suma/>
				<E2_10/>
				<E2_111/>
				<E2_112/>
				<E2_114/>
				<E2_116/>
				<E2_36/>
				<E2_34/>
				<E2_32/>
				<E2_31/>
				<E2_216/>
				<E2_214/>
				<E2_212/>
				<E2_211/>
			</sbfrmSectiuneaE>
			<sbfrmSectiuneaF>
				<sbfrmF1>
					<F1_suma/>
					<F1_suma_ded/>
					<F1_suma_scut/>
					<F1_deplata>0</F1_deplata>
					<suma_E4/>
					<suma_E3_15/>
				</sbfrmF1>
				<sbfrmF2 xfa:dataNode="dataGroup"/>
				<sbfrmF2btn>
					<tot_F2_suma>0</tot_F2_suma>
					<tot_F2_suma_ded>0</tot_F2_suma_ded>
					<tot_F2_suma_scut>0</tot_F2_suma_scut>
					<tot_F2_deplata>0</tot_F2_deplata>
					<suma_E3_21/>
					<suma_F1_F2>0</suma_F1_F2>
				</sbfrmF2btn>
			</sbfrmSectiuneaF>
			<sbfrmSectiuneaG>
				<ajDeces>
					<cnp_d/>
					<den_d/>
					<nr_act_d/>
					<data_d/>
					<cnp_b/>
					<den_b/>
					<cuantum_d/>
					<but xfa:dataNode="dataGroup"/>
				</ajDeces>
				<tot_cuantum_d/>
			</sbfrmSectiuneaG>
		</sbfrmPage1Ang>';
		return $str;
	}
	public function set_date_asigurat(){
		// print_r($this->arr_asigurati);
		$str = "";
		if($this->arr_asigurati != ""){
			$i = 0;
			$len = sizeof($this->arr_asigurati);
			$str .= '<sbfrmAntetAsig xfa:dataNode="dataGroup"/>';
			for($i; $i < $len; $i++){
				$str .= 
				'<sbfrmPage1Asig>
					<sfmDateIdentif>
						<an_r>'.$this->anul.'</an_r>
						<luna_r>'.$this->luna.'</luna_r>
						<cnp_asig>'.(isset($this->arr_asigurati[$i]['codpers'])?$this->arr_asigurati[$i]['codpers']:"").'</cnp_asig>
						<Cnp_ant>'.(isset($this->arr_asigurati[$i]['codpers'])?$this->arr_asigurati[$i]['codpers']:"").'</Cnp_ant>
						<idAsig>1</idAsig>
						<Asig_so>1</Asig_so>
						<Asig_ci>1</Asig_ci>
						<Casa_sn>'.(isset($this->arr_asigurati[$i]['casa_sn'])?$this->arr_asigurati[$i]['casa_sn']:"").'</Casa_sn>
						<Data_sf>'.(isset($this->arr_asigurati[$i]['data_sf'])?$this->arr_asigurati[$i]['data_sf']:"").'</Data_sf>
						<Data_ang>'.(isset($this->arr_asigurati[$i]['data_angaj'])?$this->arr_asigurati[$i]['data_angaj']:"").'</Data_ang>
						<Pre_ant>'.(isset($this->arr_asigurati[$i]['prenume_ant'])?$this->arr_asigurati[$i]['prenume_ant']:"").'</Pre_ant>
						<Nume_ant>'.(isset($this->arr_asigurati[$i]['Nume_ant'])?$this->arr_asigurati[$i]['Nume_ant']:"").'</Nume_ant>
						<Pren_asig>'.(isset($this->arr_asigurati[$i]['prenume'])?$this->arr_asigurati[$i]['prenume']:"").'</Pren_asig>
						<Nume_asig>'.(isset($this->arr_asigurati[$i]['nume'])?$this->arr_asigurati[$i]['nume']:"").'</Nume_asig>
						<cis_asig/>';
				if(isset($this->arr_intretinuti[$this->arr_asigurati[$i]['marca_c']])){
					$a1 = $this->arr_intretinuti[$this->arr_asigurati[$i]['marca_c']];
					$n = 1;
					$check_sot = 0;
					foreach($a1 AS $k=>$v){
						if(isset($v['calitate'])){
							if($v['calitate'] == "S"){
								$str .= 
								'<cnpSot>'.$v['codpers_intr'].'</cnpSot>
								<prenSot>'.$v['prenume_intr'].'</prenSot>
								<numeSot>'.$v['nume_intr'].'</numeSot>';
								$check_sot = 1;
							}
							else if($v['calitate'] == "A"){
								$str .= 
								'<prenParinte'.$n.'>'.$v['prenume_intr'].'</prenParinte'.$n.'>
								<numeParinte'.$n.'>'.$v['nume_intr'].'</numeParinte'.$n.'>
								<cnpParinte'.$n.'>'.$v['codpers_intr'].'</cnpParinte'.$n.'>';
								$n++;
							}
						}
					}
				}
				$str .='<asigScu/>
						<Exc7>0</Exc7>
						<asigExc>2</asigExc>
					</sfmDateIdentif>
					<det1>
						<det2>
							<stat_detasat/>
							<cif_detasat/>
							<bifa_UE>0</bifa_UE>
							<bifa_altstat>0</bifa_altstat>
							<acord_NU>0</acord_NU>
							<acord_DA>0</acord_DA>
							<dataD2/>
							<dataD1/>
							<tfNrCrt>1</tfNrCrt>
							<detasat/>
						</det2>
						<plata_CAS>0</plata_CAS>
						<plata_CASS>0</plata_CASS>
						<plata_CAM>0</plata_CAM>
					</det1>
					<sfmButoane>
						<rbl2>';
				if(stripos($this->arr_asigurati[$i]['sectiune'], 'C') !== false){$str .= '<rbC>3</rbC>';}
				if(stripos($this->arr_asigurati[$i]['sectiune'], 'B') !== false){$str .= '<rbB>2</rbB>';}
				if(stripos($this->arr_asigurati[$i]['sectiune'], 'A') !== false){$str .= '<rbA>1</rbA>';}
					$str .= '
						</rbl2>
						<tfNZL/>
						<flag/>
						<rbl/>
					</sfmButoane>
					<sbfrmSectiuneaA>
						<A_2>'.$this->arr_asigurati[$i]['pensionar'].'</A_2>
						<A_3>'.$this->arr_asigurati[$i]['tip_norma'].'</A_3>
						<A_4>'.$this->arr_asigurati[$i]['norma_info_a'].'</A_4>
						<A_6>'.$this->arr_asigurati[$i]['ore_lucrate'].'</A_6>
						<A_7>'.$this->arr_asigurati[$i]['ore_suspendate'].'</A_7>
						<A_8>'.$this->arr_asigurati[$i]['tot_zile_lucrate'].'</A_8>
						<A_13>0</A_13>
						<A_11>0</A_11>
						<A_12/>
						<A_14/>
						<A_5>0</A_5>
						<A_9>'.$this->arr_asigurati[$i]['b_som_s'].'</A_9>
						<A_1>1</A_1>
						<tichete_A>'.$this->arr_asigurati[$i]['val_tichete'].'</tichete_A>
						<VB_A>'.$this->arr_asigurati[$i]['total_brut'].'</VB_A>
						<calc_aut/>
						<facilitati>
							<A_13f>0</A_13f>
							<A_11f>0</A_11f>
							<A_12f>0</A_12f>
							<A_14f>0</A_14f>
							<A_5f>0</A_5f>
							<A_13i>0</A_13i>
							<A_11i>0</A_11i>
							<A_12i>0</A_12i>
							<A_14i>0</A_14i>
							<A_5i>0</A_5i>
							<SalBrut_A>0</SalBrut_A>
							<A_81>0</A_81>
							<A_82>0</A_82>
							<salbr_calc/>
						</facilitati>
					</sbfrmSectiuneaA>
					<sbfrmSectiuneaB>
						<calc_aut>1</calc_aut>
						<sbfrmSectiuneaB1rep>
							<sbfrmSectiuneaB1>
								<B1_1>1</B1_1>
								<B1_2>0</B1_2>
								<B1_3>N</B1_3>
								<B1_4>8</B1_4>
								<B1_6/>
								<B1_7/>
								<B1_9>0</B1_9>
								<tfNrCrt>1</tfNrCrt>
								<B1_15>0</B1_15>
								<B1_5>0</B1_5>
								<B1_10>0</B1_10>
								<tichete_B>0</tichete_B>
								<B1_8>0</B1_8>
								<B1_16/>
								<B1_17/>
								<B1_18/>
								<adaug xfa:dataNode="dataGroup"/>
								<VB_B>0</VB_B>
								<SalBrut_B1>0</SalBrut_B1>
							</sbfrmSectiuneaB1>
						</sbfrmSectiuneaB1rep>
						<SbfrmSectiuneaB2>
							<B2_1/>
							<B2_2>0</B2_2>
							<B2_3/>
							<B2_4/>
							<B2_5>0</B2_5>
							<B2_6>0</B2_6>
							<B2_7>0</B2_7>
							<facilitati>
								<B2_6f>0</B2_6f>
								<B2_7f>0</B2_7f>
								<B2_6i>0</B2_6i>
								<B2_7i>0</B2_7i>
							</facilitati>
						</SbfrmSectiuneaB2>
						<sbfrmSectiuneaB3>
							<B3_1>0</B3_1>
							<B3_2/>
							<B3_3/>
							<B3_6>0</B3_6>
							<B3_8>0</B3_8>
							<B3_4/>
							<B3_5/>
							<B3_7>0</B3_7>
							<B3_10/>
							<B3_9/>
							<B3_11>0</B3_11>
							<B3_12>0</B3_12>
							<B3_13>0</B3_13>
							<facilitati>
								<B3_7f/>
								<B3_7i/>
							</facilitati>
						</sbfrmSectiuneaB3>
						<sbfrmSectiuneaB4>
							<B4_5>0</B4_5>
							<B4_6>0</B4_6>
							<B4_7>0</B4_7>
							<B4_8>0</B4_8>
							<B4_14>0</B4_14>
							<B4_2>0</B4_2>
							<B4_1>0</B4_1>
							<B4_17>0</B4_17>
							<B4_19>0</B4_19>
							<B4_18>0</B4_18>
							<B4_20>0</B4_20>
							<B4_aj1>0</B4_aj1>
							<B4_aj2>0</B4_aj2>
							<B4_29>0</B4_29>
							<B4_30>0</B4_30>
							<facilitati>
								<B4_5f>0</B4_5f>
								<B4_6f>0</B4_6f>
								<B4_7f>0</B4_7f>
								<B4_8f>0</B4_8f>
								<B4_14f>0</B4_14f>
								<B4_5i>0</B4_5i>
								<B4_6i>0</B4_6i>
								<B4_7i>0</B4_7i>
								<B4_8i>0</B4_8i>
								<B4_14i>0</B4_14i>
								<SalBrut_B/>
								<B_81>0</B_81>
								<B_82>0</B_82>
								<salbr_calc/>
							</facilitati>
							<B4_21>0</B4_21>
							<B4_25>0</B4_25>
							<B4_22>0</B4_22>
							<B4_26>0</B4_26>
							<B4_23>0</B4_23>
							<B4_27>0</B4_27>
							<B4_24>0</B4_24>
							<B4_28>0</B4_28>
						</sbfrmSectiuneaB4>
					</sbfrmSectiuneaB>
					<sbfrmSectiuneaC>
						<SectiuneaC>
							<ID_C>1</ID_C>
							<C_1/>
							<C_2>0</C_2>
							<C_20/>
							<C_5>0</C_5>
							<C_3>0</C_3>
							<C_17>0</C_17>
							<C_19>0</C_19>
							<C_4>0</C_4>
							<C_18>0</C_18>
							<C_8>0</C_8>
							<C_9>0</C_9>
							<C_10>0</C_10>
							<C_11>0</C_11>
							<C_21>0</C_21>
							<C_23>0</C_23>
							<C_25>0</C_25>
							<C_26>0</C_26>
							<C_22>0</C_22>
							<C_24>0</C_24>
						</SectiuneaC>
					</sbfrmSectiuneaC>
					<vezi_D xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/" xfa:dataNode="dataGroup"/>
					<sbfrmSectiuneaD>';
					if(!empty($this->arr_asigurati[$i]['MEDICALE'])){
						for($j = 0; $j < sizeof($this->arr_asigurati[$i]['MEDICALE']); $j++){
							$str .= 
							'<sbfrmSectiuneaDrep>
								<D_2>'.$this->arr_asigurati[$i]['MEDICALE'][$j]['cm_numar'].'</D_2>
								<D_3>'.$this->arr_asigurati[$i]['MEDICALE'][$j]['cm_serie_i'].'</D_3>
								<D_4>'.$this->arr_asigurati[$i]['MEDICALE'][$j]['cm_numar_i'].'</D_4>
								<D_5>'.$this->arr_asigurati[$i]['MEDICALE'][$j]['cm_data'].'</D_5>
								<D_6>'.$this->arr_asigurati[$i]['MEDICALE'][$j]['dataim'].'</D_6>
								<D_7>'.$this->arr_asigurati[$i]['MEDICALE'][$j]['datafm'].'</D_7>
								<D_13>'.$this->arr_asigurati[$i]['MEDICALE'][$j]['nr_expert'].'</D_13>
								<D_14>'.$this->arr_asigurati[$i]['MEDICALE'][$j]['zi_cm_sal'].'</D_14>
								<D_15>'.$this->arr_asigurati[$i]['MEDICALE'][$j]['zi_cm_san'].'</D_15>
								<D_16>0</D_16>
								<D_17>'.$this->arr_asigurati[$i]['MEDICALE'][$j]['tot_venit'].'</D_17>
								<D_18>'.$this->arr_asigurati[$i]['MEDICALE'][$j]['tot_zile'].'</D_18>
								<D_19/>
								<D_20>'.$this->arr_asigurati[$i]['MEDICALE'][$j]['s_cm_sal'].'</D_20>
								<D_21>'.$this->arr_asigurati[$i]['MEDICALE'][$j]['s_cm_san'].'</D_21>
								<D_23>'.$this->arr_asigurati[$i]['MEDICALE'][$j]['cod_diag'].'</D_23>
								<D_1>'.$this->arr_asigurati[$i]['MEDICALE'][$j]['cm_serie'].'</D_1>
								<tfNrCrt>1</tfNrCrt>';
								if($this->arr_asigurati[$i]['MEDICALE'][$j]['fel_cm'] == "C"){
									$str .= '<Data_CMI>'.$this->arr_asigurati[$i]['MEDICALE'][$j]['cm_data_i'].'</Data_CMI>';	
								}
								$str .= 
								'<D_11>'.$this->arr_asigurati[$i]['MEDICALE'][$j]['cod_urg'].'</D_11>
								<D_12>'.$this->arr_asigurati[$i]['MEDICALE'][$j]['cod_contag'].'</D_12>
								<D_8>'.$this->arr_asigurati[$i]['MEDICALE'][$j]['cnp_copil'].'</D_8>';
								if($this->arr_asigurati[$i]['MEDICALE'][$j]['cod_ind_info_a'] < 10){
									$str .= '<D_9>'.'0'.$this->arr_asigurati[$i]['MEDICALE'][$j]['cod_ind_info_a'].'</D_9>';
								}
								else{
									$str .= '<D_9>'.$this->arr_asigurati[$i]['MEDICALE'][$j]['cod_ind_info_a'].'</D_9>';
								}
								$str .= '<D_10>'.$this->arr_asigurati[$i]['MEDICALE'][$j]['cod_pres'].'</D_10>
							</sbfrmSectiuneaDrep>';
						}
					}
					
			$str .= '</sbfrmSectiuneaD>
					<sbfrmSectiuneaE>
						<E1_1>0</E1_1>
						<E1_2>0</E1_2>
						<E1_3>0</E1_3>
						<E1_4>0</E1_4>
						<E1_5>0</E1_5>
						<E1_6>0</E1_6>
						<E1_7>0</E1_7>
						<E2_1>0</E2_1>
						<E2_2>0</E2_2>
						<E2_3>0</E2_3>
						<E2_4>0</E2_4>
						<sbfrmSectiuneaE3>
							<ID_E>1</ID_E>
							<E3_1/>
							<E3_2/>
							<E3_3/>
							<E3_4>P</E3_4>
							<E3_5/>
							<E3_6/>
							<E3_37/>
							<E3_38/>
							<E3_39/>
							<E3_40/>
							<E3_41/>
							<E3_42/>
							<E3_45/>
							<E3_46/>
							<E3_47/>
							<E3_48/>
							<E3_49/>
							<E3_7/>
							<E3_8>0</E3_8>
							<E3_9>0</E3_9>
							<E3_10/>
							<E3_20/>
							<E3_43/>
							<E3_44/>
							<E3_18/>
							<E3_33/>
							<E3_34/>
							<E3_21/>
							<E3_35/>
							<E3_36/>
							<E3_23/>
							<E3_24/>
							<E3_25/>
							<E3_26/>
							<E3_27/>
							<E3_28/>
							<E3_29/>
							<E3_30/>
							<E3_19/>
							<E3_31/>
							<E3_22/>
							<E3_32/>
							<E3_11/>
							<E3_12/>
							<E3_13/>
							<E3_14>0</E3_14>
							<E3_15>0</E3_15>
							<E3_16>0</E3_16>
							<adaug xfa:dataNode="dataGroup"/>
						</sbfrmSectiuneaE3>
						<sbfrmSectiuneaE4_ab>
							<ID_E4>1</ID_E4>
							<cnp_ctr/>
							<nr_ctr/>
							<data_ctr/>
							<cota_ctr>0</cota_ctr>
							<suma_ctr>0</suma_ctr>
							<den/>
							<cui/>
							<cota>0</cota>
							<suma>0</suma>
							<adaug xfa:dataNode="dataGroup"/>
						</sbfrmSectiuneaE4_ab>
						<sbfrmSectiuneaE4_c>
							<Tcota>0.00</Tcota>
							<Tsuma>0</Tsuma>
							<Timp>0</Timp>
						</sbfrmSectiuneaE4_c>
					</sbfrmSectiuneaE>
					<sbfrmAllPlus xfa:dataNode="dataGroup"/>
				</sbfrmPage1Asig>';
			}
		}
		return $str;
	}

  	public function generare_D112(){
  		$file_name = "D112_".$_SESSION['cif_dsoft']."_".$_POST['G_ANUL']."_".$_POST['G_LUNA'].".xdp";
		$path = "../../users/d112/";
		$D112 = fopen($path.$file_name, "w") or die("Unable to open file!");
		
		$str = $this->str_header."\n";


		$str .= self::set_date_angajator();

		$str .= self::set_date_asigurat();


		$str .= $this->str_footer;
		
		fwrite($D112, $str);
		fclose($D112);





  		


  		
  	}
}
$action = new D112();
$action->generare_D112();
?>