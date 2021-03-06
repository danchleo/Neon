<?php
include('./includes/loader.php');

	if($LoggedIn === false){
		header("Location: index.php");
		die();
	} else {
	
		//	Start server session and find current directory.
		if (!$user_ssh->login($sUser->sUsername, $_SESSION['password'])) { exit('User Connection To Server Failed!');}
		if (!$user_sftp->login($sUser->sUsername, $_SESSION['password'])) { exit('User Connection To Server Failed!');}
		
			// Users Root Directory
			$sUser->sRootDir = '/home/'.$sUser->sUsername.'/';
			$sUser->TrashDirectory = '/home/'.$sUser->sUsername.'/.trash/';
			
			// Check if user is in a subdirectory if yes use it, if no use root path.
			if((!empty($_SESSION['current_directory'])) && (!empty($_GET['ajax']))){
				$uLastCurrentDirectory = $_SESSION['current_directory'];
			} else {
				$uLastCurrentDirectory = $sUser->sRootDir;
			}
			
			// Check if the user is going up or down directories.
			if(!empty($_GET['up'])){
				$uCurrentDirectory = $uLastCurrentDirectory.$_GET["up"].'/';
			} elseif(!empty($_GET['back'])){
				$uCurrentDirectory = dirname($uLastCurrentDirectory).'/';
			} elseif(!empty($_GET['editor'])) {
				$uCurrentDirectory = $_GET['file'];
			} elseif(!empty($_GET['download_file'])){
				$uCurrentDirectory = $_GET['download_file'];
			} elseif(!empty($_GET['download_folder'])){
				$uCurrentDirectory = $_GET['download_folder'];
			} else {
				$uCurrentDirectory = $uLastCurrentDirectory;
			}
			
			// Validate path
			$path = new PathValidator($uCurrentDirectory);
			if($path->ValidatePath($sUser->sRootDir)){
				$sCurrentDirectory = $uCurrentDirectory;
			} else {
				die("There seems to be a problem with your request. Please go back and try again.");
			}
			
			// Check if root directory for back.
			if($sUser->sRootDir != $sCurrentDirectory){
				$sAllowBack = true;
			} else {
				$sAllowBack = false;
			}
			
			// Save path data for future use
			$_SESSION['current_directory'] = $sCurrentDirectory;
			
			// If editor, load editor and file.
			if(!empty($_GET['editor'])){
				$sSaveName = basename($sCurrentDirectory);
				$sDirectorySave = dirname($sCurrentDirectory);
				$sFileName = $sCurrentDirectory;
				if(!empty($_GET['save'])){
					$sSavingCode = random_string(15);
					$uPostContent = $_POST['content'];
					$file_contents = $user_ssh->exec("cat >".$sFileName." <<".$sSavingCode."
".$uPostContent);
				echo "File Has Been Saved!";
				} else {
					$sFileContent = $user_ssh->exec('cat "'.$sCurrentDirectory.'"');
					$sCheckNonExistant = "cat: ".$sCurrentDirectory.": No such file or directory";
					if(stristr($sFileContent, $sCheckNonExistant)){
						$sFileContent = "";
					}
					$sEditor = Templater::AdvancedParse('/blue_default/edit', $locale->strings, array(
						'PanelTitle'  => $sPanelTitle->sValue,
						'ErrorMessage'	=>	"",
						'Username'	=>	$sUser->sUsername,
						'FileName'	=>	$sFileName,
						'FilePath'	=>	$sCurrentDirectory,
						'FileContent'	=>	$sFileContent,
						'DefaultEditorTheme'	=>	$sUser->sDefaultEditorTheme
					));
					echo $sEditor;
				}
			} elseif(!empty($_GET['upload'])){
				include('./includes/upload.php');
			} elseif(!empty($_GET['download_file'])){
				$sCurrentFile = basename($sCurrentDirectory);
				$sCurrentPath = dirname($sCurrentDirectory);
				if (!is_dir('downloads/'.$sUser->sUsername.'/')) {
					mkdir('downloads/'.$sUser->sUsername.'/');
				}
				unlink('downloads/'.$sUser->sUsername.'/download.*.zip');
				$sFileContent = $user_ssh->exec('cd '.$sCurrentPath.'; zip -r '.$sUser->sRootDir.'download.'.$sTimestamp.'.zip '.$sCurrentFile);
				$user_sftp->get($sUser->sRootDir.'download.'.$sTimestamp.'.zip', 'downloads/'.$sUser->sUsername.'/download.'.$sTimestamp.'.zip');
				$sFileContent = $user_ssh->exec('rm -rf '.$sUser->sRootDir.'download.'.$sTimestamp.'.zip');
				header("Location: downloads/$sUser->sUsername/download.$sTimestamp.zip");
			} elseif(!empty($_GET['download_folder'])){
				if (!is_dir('downloads/'.$sUser->sUsername.'/')) {
					mkdir('downloads/'.$sUser->sUsername.'/');
				}
				unlink('downloads/'.$sUser->sUsername.'/download.*.zip');
				$sFileContent = $user_ssh->exec('cd '.$sUser->sRootDir.'; zip -r '.$sUser->sRootDir.'download.'.$sTimestamp.'.zip '.$sCurrentDirectory);
				$user_sftp->get($sUser->sRootDir.'download.'.$sTimestamp.'.zip', 'downloads/'.$sUser->sUsername.'/download.'.$sTimestamp.'.zip');
				$sFileContent = $user_ssh->exec('rm -rf '.$sUser->sRootDir.'download.'.$sTimestamp.'.zip');
				header("Location: downloads/$sUser->sUsername/download.$sTimestamp.zip");
			} else {
			
				// Create directories and files
				if(!empty($_GET['add_folder'])){
					$sFolderName = preg_replace("/[^a-z0-9_ .-]/i", "", $_GET['add_folder']);
					if(!empty($sFolderName)){
						$sCreateFolder = $user_ssh->exec("mkdir '".$sCurrentDirectory.$sFolderName."'");
					}
				}
				if(!empty($_GET['add_file'])){
					$sFileName = preg_replace("/[^a-z0-9_ .-]/i", "", $_GET['add_file']);
					if(!empty($sFileName)){
						$sCreateFile = $user_ssh->exec('echo -n " " >> \''.$sCurrentDirectory.$sFileName.'\'');
					}
				}
				
				// Delete files/folders.
				if(!empty($_GET['delete'])){
					$sDelete = preg_replace("/[^a-z0-9_ .-]/i", "", $_GET['delete']);
					if(!empty($sDelete)){
						$sCreateFolder = $user_ssh->exec('cd "'.$sCurrentDirectory.'"; mv "'.$sDelete.'" '.$sUser->TrashDirectory);
					}
				}
			
				// Pull directory data.
				$sPullDirectories = $user_ssh->exec('ls -l "'.$sCurrentDirectory.'" |grep ^d');
				$sParsedDirectories = preg_split('/\r\n|\r|\n/', $sPullDirectories);
				$sDirectories = array();
				foreach($sParsedDirectories as $sValue){
					if(!empty($sValue)){
						$sValue = preg_replace('/\s+/', ' ',$sValue);
						$sValues = explode(" ", $sValue);
						if(count($sValues) > 8){
							foreach($sValues as $key => $value){
								if($key == 8){
								$sDirectoryName = str_replace("/","", $value);
								} elseif($key >= 9){
								$sDirectoryName .= " ".str_replace("/","", $value);
								}
							}
							$sPullSize = $user_ssh->exec('du -hs "'.$sCurrentDirectory.$sDirectoryName.'/"');
							$sSizeValues = explode("	", $sPullSize);
							array_push($sDirectories, array("name" => trim($sDirectoryName), "size" => trim($sSizeValues[0])));
						}
						unset($values);
						if(!empty($sDirectoryName)){
							unset($sDirectoryName);
						}
						$_SESSION['directory_tree'] = $sDirectories;
					}
				}
			
				$sPullFiles = $user_ssh->exec('ls -lsph1 "'.$sCurrentDirectory.'" | grep -v "\/"');
				$sParsedFiles = preg_split('/\r\n|\r|\n/', $sPullFiles);
				$sFiles = array();
				foreach($sParsedFiles as $value){
					if(!empty($value)){
						$value = preg_replace('/\s+/', ' ',$value);
						$value = preg_replace('/^ /', '', $value);
						$values = explode(" ", $value);
						if(count($values) > 8){
							foreach($values as $key => $value){
								if($key == 9){
								$sFileName = str_replace("/","", $value);
								} elseif($key >= 10){
								$sFileName .= " ".str_replace("/","", $value);
								}
							}
							$sFileSize = $values[5];
							if(is_numeric($sFileSize)){
								$sFileSize = $sFileSize.'.0B';
							}
							array_push($sFiles, array("name" => trim($sFileName), "size" => trim($sFileSize)));
							unset($values);
						}
					}
				}
			
				// Load filemanager.		
				$sFileManager = Templater::AdvancedParse('/blue_default/fmlayout', $locale->strings, array(
					'PanelTitle'  => $sPanelTitle->sValue,
					'ErrorMessage'	=>	"",
					'Username'	=>	$sUser->sUsername,
					'Directories'	=>	$sDirectories,
					'Files'	=>	$sFiles,
					'MaxListFiles' =>	$sUser->sMaxListFiles,
					'AllowBack'	=>	$sAllowBack,
					'FilePath'	=>	$sCurrentDirectory,
					'MaxPanelUploadSize'	=>	$sMaxPanelUploadSize->sValue,
				));
		
				// If not ajax load up data normally, if ajax load json.
				if(!isset($_GET['ajax'])){
					$sContent = Templater::AdvancedParse('/blue_default/filemanager', $locale->strings, array(
						'PanelTitle'  => $sPanelTitle->sValue,
						'ErrorMessage'	=>	"",
						'Username'	=>	$sUser->sUsername,
						'FileManagerCode'	=> $sFileManager
					));
					echo Templater::AdvancedParse('/blue_default/master', $locale->strings, array(
						'PageTitle'  => "File Manager",
						'PageName'	=>	"filemanager",
						'PanelTitle'	=>	$sPanelTitle->sValue,
						'ErrorMessage'	=>	"",
						'Username'	=>	$sUser->sUsername,
						'Content'	=>	$sContent
					));
				} else {
					$sContent = preg_replace('/\r\n|\r|\n/', '', $sFileManager);
					$sReturnArray = array("content"	=>	$sContent);
					echo json_encode($sReturnArray);
				}
			}	
	}