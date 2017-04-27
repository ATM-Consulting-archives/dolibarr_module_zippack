<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    class/actions_zippack.class.php
 * \ingroup zippack
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */

/**
 * Class ActionsZippack
 */



class ActionsZippack
{
	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * Constructor
	 */
	public function __construct()
	{
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	function doActions($parameters, &$object, &$action, $hookmanager)
	{
		$error = 0; // Error counter
		$myvalue = ''; // A result value
		if (in_array('invoicelist', explode(':', $parameters['context'])))
		{
			global $langs,$db,$conf,$show_files,$user;
			$langs->load('zippack@zippack');
			
			if(GETPOST('massaction')=='masszip') {
			
				$show_files = 1;
				
				$toselect= GETPOST('toselect');
				
				dol_include_once('/core/lib/files.lib.php');
				dol_include_once('/core/lib/date.lib.php');
				
				$objectclass = get_class($object);
				
				if($objectclass=='facture')$objectlabel='Invoice';
				else if($objectclass=='commande')$objectlabel='Order';
				else if($objectclass=='propale')$objectlabel='Proposal';
				else $objectlabel=ucfirst($objectclass);
				
				$objecttmp=new $objectclass($db);
				$listofobjectid=array();
				$listofobjectthirdparties=array();
				$listofobjectref=array();
				foreach($toselect as $toselectid)
				{
					$objecttmp=new $objectclass($db);	// must create new instance because instance is saved into $listofobjectref array for future use
					$result=$objecttmp->fetch($toselectid);
					if ($result > 0)
					{
						$listoinvoicesid[$toselectid]=$toselectid;
						$thirdpartyid=$objecttmp->fk_soc?$objecttmp->fk_soc:$objecttmp->socid;
						$listofobjectthirdparties[$thirdpartyid]=$thirdpartyid;
						$listofobjectref[$toselectid]=$objecttmp->ref;
					}
				}
				
				$uploaddir = $conf->{$object->element}->dir_output . '/';
				
				$arrayofinclusion=array();
				foreach($listofobjectref as $tmppdf) $arrayofinclusion[]=preg_quote($tmppdf.'.pdf','/');
				$listoffiles = dol_dir_list($uploaddir,'all',1,implode('|',$arrayofinclusion),'\.meta$|\.png','date',SORT_DESC,0,true);
				
				// build list of files with full path
				$files = array();
				foreach($listofobjectref as $basename)
				{
					foreach($listoffiles as $filefound)
					{
						if (strstr($filefound["name"],$basename))
						{
							$files[] = $uploaddir.'/'.$basename.'/'.$filefound["name"];
							break;
						}
					}
				}
			
				$diroutputmassaction=$conf->{$object->element}->dir_output . '/temp/massgeneration/'.$user->id;
				dol_mkdir($diroutputmassaction);
				
				// Save merged file
				$filename=strtolower(dol_sanitizeFileName($langs->transnoentities($objectlabel)));
				if ($filter=='paye:0')
				{
					if ($option=='late') $filename.='_'.strtolower(dol_sanitizeFileName($langs->transnoentities("Unpaid"))).'_'.strtolower(dol_sanitizeFileName($langs->transnoentities("Late")));
					else $filename.='_'.strtolower(dol_sanitizeFileName($langs->transnoentities("Unpaid")));
				}
				if ($year) $filename.='_'.$year;
				if ($month) $filename.='_'.$month;
				if (count($files)>0)
				{
					
					$now=dol_now();
					$filezip=$diroutputmassaction.'/'.$filename.'_'.dol_print_date($now,'dayhourlog').'.zip';
					
					if (defined('ODTPHP_PATHTOPCLZIP'))
					{
						include_once ODTPHP_PATHTOPCLZIP.'/pclzip.lib.php';
						
						if(file_exists($filezip))unlink($filezip);
						
						$archive = new PclZip($filezip);
						$res = $archive->add($files, PCLZIP_OPT_REMOVE_ALL_PATH);
						
						$langs->load("exports");
						setEventMessages($langs->trans('FileSuccessfullyBuilt',$filename.'_'.dol_print_date($now,'dayhourlog')), null, 'mesgs');
						
					}
					else {
						$this->errors[] = $langs->trans('ZipPackUnableToLocalizePCLZIP');
						$error++;
					}
					
				}
				else
				{
					setEventMessages($langs->trans('NoPDFAvailableForDocGenAmongChecked'), null, 'errors');
				}
				
			}
			
			
		}

		if (! $error)
		{
			//$this->results = array('myreturn' => $myvalue);
			//$this->resprints = '';
			return 0; // or return 1 to replace standard code
		}
		else
		{
			
			return -1;
		}
	}
	
	function printFieldListFooter($parameters, &$object, &$action, $hookmanager)
	{
		
		$error = 0; // Error counter
		$myvalue = ''; // A result value
		if (in_array('invoicelist', explode(':', $parameters['context'])))
		{
			global $langs;
			?>
			<script type="text/javascript">
			$(document).ready(function() {
				$('select[name=massaction]').append('<option value="masszip"><?php echo addslashes($langs->trans('ConcatAsZip')) ?></option>');
				
			});
			</script>
			<?php 
			
		}
		
		if (! $error)
		{
			//$this->results = array('myreturn' => $myvalue);
			//$this->resprints = '';
			return 0; // or return 1 to replace standard code
		}
		else
		{
			$this->errors[] = 'Error message';
			return -1;
		}
	}
}