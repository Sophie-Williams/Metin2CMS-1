<?php
	session_start();
	
	header('Cache-control: private');
	
	$current_page = isset($_GET['p']) ? $_GET['p'] : null;
		
	include 'config.php';
	
	if (substr($site_url, -1)!='/')
		$site_url.='/';
	
	$site_domain = $_SERVER['HTTP_HOST'];
	
	include 'include/functions/version.php';
	
	include 'include/functions/language.php';
	
	require_once("include/classes/user.php");
	
	$jsondata = file_get_contents('include/db/settings.json');
	$jsondata = json_decode($jsondata,true);
	$jsondataRanking = file_get_contents('include/db/ranking.json');
	$jsondataRanking = json_decode($jsondataRanking,true);
	include 'include/functions/json.php';
	$site_title = getJsonSettings("title");
	$paypal_email = getJsonSettings("paypal");
	$forum=getJsonSettings("forum", "links");
	$support=getJsonSettings("support", "links");
	$item_shop=getJsonSettings("item-shop", "links");
	$top10backup_day=getJsonSettings("day", "top10backup");
	$top10backup_month=getJsonSettings("month", "top10backup");
	$top10backup_year=getJsonSettings("year", "top10backup");
	
	include 'include/functions/social-links.php';
	$social_links=getJsonSettings("", "social-links");
	$social_links=getSocialLinks();

	$offline = 0;
	
	$database = new USER($host, $user, $password);
	
	include 'include/functions/pages.php';

	if($page=='news' || $page=='read')
	{
		require_once("include/classes/news.php");
		$paginate = new paginate();
		if($page=='read')
		{
			$read_id = isset($_GET['no']) ? $_GET['no'] : null;
			if(is_numeric($read_id))
			{
				$exist = $paginate->check_id($read_id);
				if($exist==0)
				{
					header("Location: ".$site_url);
					die();
				} else if($exist==1)
				{
					$article = $paginate->read($read_id);
					$title = $article['title'];
				}
			}
		}
	}
	
	$jsondataPrivileges['news']=9;
	
	if(!$offline)
	{
		include 'include/functions/basic.php';
		
		if($database->is_loggedin())
		{
			if(($_SESSION['fingerprint']!=md5($_SERVER['HTTP_USER_AGENT'].'x'.$_SERVER['REMOTE_ADDR'])) || ($_SESSION['password']!=securityPassword(getAccountPassword($_SESSION['id']))))
			{
				$database->doLogout();
				header("Location: ".$site_url);
				die();
			}
			$web_admin = web_admin_level();
		} else $web_admin = 0;

		if($web_admin)
		{
			$jsondataPrivileges = file_get_contents('include/db/privileges.json');
			$jsondataPrivileges = json_decode($jsondataPrivileges,true);
		}
		
		if($database->is_loggedin() && $web_admin>=$jsondataPrivileges['news'])
		{
			$delete = isset($_GET['delete']) ? $_GET['delete'] : null;
			if(is_numeric($delete))
			{
				$paginate->delete_article($delete);
				header("Location: ".$site_url);
				die();
			}
		}
		
		$jsondataFunctions = file_get_contents('include/db/functions.json');
		$jsondataFunctions = json_decode($jsondataFunctions, true);
		
		$statistics = false;
		foreach($jsondataFunctions as $key => $status)
			if($key != 'active-registrations' && $key != 'players-debug' && $key != 'active-referrals' && $status)
			{
				$statistics = true;
				break;
			}
		
		if($current_page=="logout")
		{
			$database->doLogout();
			header("Location: ".$site_url);
			die();
		} else if($page=='players')
		{
			if(isset($_POST['search']) && strlen($_POST['search'])>=3)
			{
				header("Location: ".$site_url."ranking/players/1/".$_POST['search']);
				die();
			} else if(isset($_POST['search']) && $_POST['search']=='')
			{
				header("Location: ".$site_url."ranking/players/1");
				die();
			}
			
			if(isset($_GET['player_name']))
			{
				$new_search = strip_tags($_GET['player_name']);
				if(strlen($new_search)>=3)
					$search = $new_search;
			}
			
			require_once("include/classes/players.php");
			$paginate = new paginate();
		} else if($page=='guilds')
		{
			if(isset($_POST['search']) && strlen($_POST['search'])>=3)
			{
				header("Location: ".$site_url."ranking/guilds/1/".$_POST['search']);
				die();
			} else if(isset($_POST['search']) && $_POST['search']=='')
			{
				header("Location: ".$site_url."ranking/guilds/1");
				die();
			}
			
			if(isset($_GET['guild_name']))
			{
				$new_search = strip_tags($_GET['guild_name']);
				if(strlen($new_search)>=3)
					$search = $new_search;
			}
			
			require_once("include/classes/guilds.php");
			$paginate = new paginate();
		} else if($page=='login')
		{
			include 'include/functions/login.php';
		} else if($page=='lost')
		{
			include 'include/functions/lost.php';
		} else if($page=='download')
		{
			$jsondataDownload = file_get_contents('include/db/download.json');
			$jsondataDownload = json_decode($jsondataDownload, true);
		} else if($page=='donate')
		{
			$jsondataDonate = file_get_contents('include/db/donate.json');
			$jsondataDonate = json_decode($jsondataDonate, true);
			
			$jsondataCurrency = file_get_contents('include/db/currency.json');
			$jsondataCurrency = json_decode($jsondataCurrency,true);
			$currency = $jsondataCurrency[$jsondata['general']['currency']]['code'];
			
			if(isset($_POST["method"]) && strtolower($_POST["method"])=='paypal' && isset($_POST['id']) && isset($_POST['type']))
			{
				$return_url = $site_url."index.php";
				$cancel_url = $site_url."index.php";
				$notify_url = $site_url."paypal.php";
				
				$querystring = '';
				$querystring .= "?business=".urlencode($paypal_email)."&";
				
				$price = $jsondataDonate[$_POST['id']]['list'][$_POST['type']];
				
				$querystring .= "item_name=".urlencode($jsondataDonate[$_POST['id']]['name'].' ['.$price['price'].' - '.$price['md'].' MD]')."&";
				$querystring .= "amount=".urlencode($price['price'])."&";
				
				$querystring .= "cmd=".urlencode(stripslashes("_xclick"))."&";
				$querystring .= "no_note=".urlencode(stripslashes("1"))."&";
				$querystring .= "currency_code=".urlencode(stripslashes($jsondata['general']['currency']))."&";
				$querystring .= "bn=".urlencode(stripslashes("PP-BuyNowBF:btn_buynow_LG.gif:NonHostedGuest"))."&";
				$querystring .= "first_name=".urlencode(stripslashes(getAccountName($_SESSION['id'])))."&";
				
				$querystring .= "return=".urlencode(stripslashes($return_url))."&";
				$querystring .= "cancel_return=".urlencode(stripslashes($cancel_url))."&";
				$querystring .= "notify_url=".urlencode($notify_url)."&";
				$querystring .= "item_number=".urlencode($jsondataDonate[$_POST['id']]['name'].' ['.$price['price'].' - '.$price['md'].' MD]')."&";
				$querystring .= "custom=".urlencode($_SESSION['id']);
				
				//redirect('https://www.sandbox.paypal.com/cgi-bin/webscr'.$querystring);
				$url = 'https://www.paypal.com/cgi-bin/webscr'.$querystring;
				if(!headers_sent()) {
					header('Location: '.$url);
					exit;
				} else {
					echo '<script type="text/javascript">';
					echo 'window.location.href="'.$url.'";';
					echo '</script>';
					echo '<noscript>';
					echo '<meta http-equiv="refresh" content="0;url='.$url.'" />';
					echo '</noscript>';
					exit;
				}
				
				exit();
			}
		}
		redirect($page);

		if($page=='administration')
			include 'include/functions/administration.php';
		else if($page=='password')
			include 'include/functions/password.php';
		else if($page=='email')
			include 'include/functions/email.php';
		else if($page=='vote4coins')
			include 'include/functions/vote4coins.php';
		else if($page=='referrals')
			include 'include/functions/referrals.php';
		else if($page=='redeem')
			include 'include/functions/redeem.php';
		else if($page=='admin')
		{
			$admin_page = isset($_GET['a']) ? $_GET['a'] : null;
			include 'include/functions/admin-pages.php';

			checkPrivileges($a_page, $web_admin);

			if($admin_page=='log')
			{
				$tables = getLogTables();

				$current_log = isset($_GET['log']) ? $_GET['log'] : null;

				if($current_log && !in_array($current_log, $tables))
				{
					header("Location: ".$site_url."admin/log");
					die();
				} else if($current_log)
				{
					require_once("include/classes/log.php");
					$paginate = new paginate();
					$columns = getColumnsLog($current_log);
				}
			}
			else if($admin_page=='links')
			{
				$jsondataCurrency = file_get_contents('include/db/currency.json');
				$jsondataCurrency = json_decode($jsondataCurrency,true);

				if(isset($_POST['submit']))
				{
					$edited = false;
					
					foreach($_POST as $key=>$link)
						if(isset($jsondata['general'][$key]))
						{
							if($jsondata['general'][$key]!=$link)
							{
								$jsondata['general'][$key]=$link;
								$edited = true;
							}
						}
						else if(isset($jsondata['links'][$key]))
						{
							if($jsondata['links'][$key]!=$link)
							{
								$jsondata['links'][$key]=$link;
								$edited = true;
							}
						}
						else if(isset($jsondata['social-links'][$key]))
						{
							if($jsondata['social-links'][$key]!=$link)
							{
								$jsondata['social-links'][$key]=$link;								
								$edited = true;
							}
						}
					if($edited)
					{
						$json_new = json_encode($jsondata);
						file_put_contents('include/db/settings.json', $json_new);
					}
					header("Location: ".$site_url.'admin/links');
					die();
				}
			} else if($admin_page=='players')
			{
				if(isset($_POST['search']) && strlen($_POST['search'])>=3)
				{
					header("Location: ".$site_url."admin/players/1/".$_POST['search']);
					die();
				} else if(isset($_POST['search']) && $_POST['search']=='')
				{
					header("Location: ".$site_url."admin/players/1");
					die();
				}
				
				if(isset($_GET['player_name']))
				{
					$new_search = strip_tags($_GET['player_name']);
					if(strlen($new_search)>=3)
						$search = $new_search;
				}
				
				require_once("include/classes/admin-players.php");
				$paginate = new paginate();
				
				if(isset($_POST['permanent']) && isset($_POST['accountID']))
				{
					banPermanent(intval($_POST['accountID']), $_POST['permanent']);
					
					$location = '';
					if(isset($_GET["page_no"]) && is_numeric($_GET["page_no"]) && $_GET["page_no"]>1)
						$location = $_GET["page_no"];
					else $location = 1;
					if($search)
						$location.= '/'.$search;
					
					header("Location: ".$site_url."admin/players/".$location);
					die();
				}
				else if(isset($_POST['unban']) && isset($_POST['accountID']))
				{
					unBan(intval($_POST['accountID']));
					
					$location = '';
					if(isset($_GET["page_no"]) && is_numeric($_GET["page_no"]) && $_GET["page_no"]>1)
						$location = $_GET["page_no"];
					else $location = 1;
					if($search)
						$location.= '/'.$search;
					
					header("Location: ".$site_url."admin/players/".$location);
					die();
				} else if(isset($_POST['temporary']) && isset($_POST['accountID']) && isset($_POST['months']) && isset($_POST['days']) && isset($_POST['hours']) && isset($_POST['minutes']) && check_account_column('availDt'))
				{
					$time_availDt = strtotime("now +".intval($_POST['months'])." month +".intval($_POST['days'])." day +".intval($_POST['hours'])." hours +".intval($_POST['minutes'])." minute");
					banTemporary(intval($_POST['accountID']), $_POST['temporary'], $time_availDt);
					
					$location = '';
					if(isset($_GET["page_no"]) && is_numeric($_GET["page_no"]) && $_GET["page_no"]>1)
						$location = $_GET["page_no"];
					else $location = 1;
					if($search)
						$location.= '/'.$search;
					
					header("Location: ".$site_url."admin/players/".$location);
					die();
				}
			} else if($admin_page=='redeem')
			{
				require_once("include/classes/admin-redeem-codes.php");
				$paginate = new paginate();
				
				if(isset($_POST['delete']) && isset($_POST['id']))
				{
					delete_redeeem_code($_POST['id']);
					$location = '';
					if(isset($_GET["page_no"]) && is_numeric($_GET["page_no"]) && $_GET["page_no"]>1)
						$location = $_GET["page_no"];
					else $location = 1;
					
					header("Location: ".$site_url."admin/redeem/".$location);
					die();
				}
			}
			else if($admin_page=='player_edit')
			{
				$player_id = isset($_GET['id']) ? $_GET['id'] : null;
				if(!check_char($player_id))
				{
					header("Location: ".$site_url."admin/players");
					die();	
				} else {
					$columns = getCharColumns('player');
					foreach($columns as $key => $column)
					{
						$type = translateNativeType($column['native_type']);
						if(!($type=='int' || $type=='string') || $column['name']=='id')
							unset($columns[$key]);
					}
					
					$actual_data = getCharData($player_id);

					$empire = get_player_empire($actual_data['account_id']);

					
					if(isset($_POST['submit']))
					{
						if($actual_data['name'] != $_POST['name'] && check_char_name($_POST['name']))
						{
							$triedName = $_POST['name'];
							$_POST['name'] = $actual_data['name'];
						}
						
						updateChar($player_id, $columns, $actual_data);
						updateWebAdmin($actual_data['account_id'], $_POST['web_admin']);
						updateGameAdmin(getAccountName($actual_data['account_id']), $actual_data['name'], $_POST['mAuthority']);
						update_empire($actual_data['account_id'], $_POST['empire']);
					}
				}
			}
			else if($admin_page=='createitems')
			{
				$jsonBonuses = file_get_contents('include/db/bonuses.json');
				$jsonBonuses = json_decode($jsonBonuses,true);
				
				$form_bonuses = '';
				foreach($jsonBonuses as $bonus)
					$form_bonuses .= '<option value='.$bonus['id'].'>'.str_replace("[n]", 'XXX', $bonus[$language_code]).'</option>';
			}
			else if($admin_page=='download')
			{
				$jsondataDownload = file_get_contents('include/db/download.json');
				$jsondataDownload = json_decode($jsondataDownload, true);
				
				if(!$jsondataDownload)
					$jsondataDownload = array();
				
				if(isset($_POST['submit']))
				{
					$new_link = array();
					$new_link['name'] = $_POST['download_server'];
					$new_link['link'] = $_POST['download_link'];
					
					array_push($jsondataDownload, $new_link);
					
					$json_new = json_encode($jsondataDownload);
					file_put_contents('include/db/download.json', $json_new);
					
					header("Location: ".$site_url.'admin/download');
					die();
				} else if(isset($_GET['del']))
				{
					unset($jsondataDownload[$_GET['del']]);
					
					$json_new = json_encode($jsondataDownload);
					file_put_contents('include/db/download.json', $json_new);
					
					header("Location: ".$site_url.'admin/download');
					die();
				}
			}
			else if($admin_page=='vote4coins')
			{
				$jsondataVote4Coins = file_get_contents('include/db/vote4coins.json');
				$jsondataVote4Coins = json_decode($jsondataVote4Coins, true);
				
				if(!$jsondataVote4Coins)
					$jsondataVote4Coins = array();
				
				if(isset($_POST['submit']))
				{
					$new_link = array();
					$new_link['name'] = $_POST['site_name'];
					$new_link['link'] = $_POST['site_link'];
					$new_link['type'] = $_POST['type'];
					$new_link['value'] = $_POST['coins'];
					$new_link['time'] = $_POST['time'];
					
					array_push($jsondataVote4Coins, $new_link);
					
					$json_new = json_encode($jsondataVote4Coins);
					file_put_contents('include/db/vote4coins.json', $json_new);
					
					header("Location: ".$site_url.'admin/vote4coins');
					die();
				} else if(isset($_GET['del']))
				{
					unset($jsondataVote4Coins[$_GET['del']]);
					
					$json_new = json_encode($jsondataVote4Coins);
					file_put_contents('include/db/vote4coins.json', $json_new);
					
					delete_vote4coins($_GET['del']);
					
					header("Location: ".$site_url.'admin/vote4coins');
					die();
				}
			}
			else if($admin_page=='functions' && isset($_POST['submit']))
			{
				$edited = false;
				
				foreach($_POST as $key=>$value)
					if(isset($jsondataFunctions[$key]))
						if($jsondataFunctions[$key]!=$value)
						{
							$jsondataFunctions[$key]=$value;
							$edited = true;
						}
				
				if($edited)
				{
					$json_new = json_encode($jsondataFunctions);
					file_put_contents('include/db/functions.json', $json_new);
				}
				
				header("Location: ".$site_url.'admin/functions');
				die();
			} else if($admin_page=='referrals')
			{
				$jsondataReferrals = file_get_contents('include/db/referrals.json');
				$jsondataReferrals = json_decode($jsondataReferrals, true);
				
				if(isset($_POST['submit']))
				{
					$edited = false;
					
					if(isset($_POST['status']) && $jsondataFunctions['active-referrals']!=$_POST['status'])
					{
						$jsondataFunctions['active-referrals']=$_POST['status'];
						
						$json_new = json_encode($jsondataFunctions);
						file_put_contents('include/db/functions.json', $json_new);
					}
					
					foreach($_POST as $key=>$value)
						if(isset($jsondataReferrals[$key]))
							if($jsondataReferrals[$key]!=$value)
							{
								$jsondataReferrals[$key]=$value;
								$edited = true;
							}
					
					if($edited)
					{
						$json_new = json_encode($jsondataReferrals);
						file_put_contents('include/db/referrals.json', $json_new);
					}
					
					header("Location: ".$site_url.'admin/referrals');
					die();
				}
			}
			else if($admin_page=='privileges')
			{
				if(isset($_POST['submit']))
				{
					$edited = false;
					
					foreach($_POST as $key=>$value)
						if(isset($jsondataPrivileges[$key]))
							if($jsondataPrivileges[$key]!=$value)
							{
								$jsondataPrivileges[$key]=$value;
								$edited = true;
							}
					
					if($edited)
					{
						$json_new = json_encode($jsondataPrivileges);
						file_put_contents('include/db/privileges.json', $json_new);
					}
					
					header("Location: ".$site_url.'admin/privileges');
					die();
				}
			}
			else if($admin_page=='language')
			{
				if(isset($_POST['default-language']))
				{
					$edited = false;
					
					if(isset($json_languages['languages'][$_POST['default-language']]) && $_POST['default-language'] != $json_languages['settings']['default'])
					{
						$json_languages['settings']['default'] = $_POST['default-language'];
						$edited = true;
					}
					
					if($edited)
					{
						$json_new = json_encode($json_languages);
						file_put_contents('include/db/languages.json', $json_new);
					}
					
					header("Location: ".$site_url.'admin/language');
					die();
				} else if(isset($_POST['delete']))
				{
					$edited = false;
					if(isset($json_languages['languages'][$_POST['delete']]) && $_POST['delete'] != $json_languages['settings']['default'])
					{
						unset($json_languages['languages'][$_POST['delete']]);
						unlink('include/languages/'.$_POST['delete'].'.php');
						$edited = true;
					}
					
					if($edited)
					{
						$json_new = json_encode($json_languages);
						file_put_contents('include/db/languages.json', $json_new);
					}
					
					header("Location: ".$site_url.'admin/language');
					die();
				} else if(isset($_POST['install']) && isset($_POST['name']) && isset($_POST['link']))
				{
					$edited = false;
					$file = 'update.zip';
					@file_put_contents($file, file_get_contents($_POST['link']));

					if(file_exists($file)) {
						$path = pathinfo(realpath($file), PATHINFO_DIRNAME);

						$zip = new ZipArchive;
						$res = $zip->open($file);
						if($res === TRUE) {
							$zip->extractTo($path);
							$zip->close();
							
							if(file_exists($file)) {
								unlink($file);
							}
							
							if(!isset($json_languages['languages'][$_POST['install']]))
							{
								$json_languages['languages'][$_POST['install']] = $_POST['name'];
								$edited = true;
							}
							
							if($edited)
							{
								$json_new = json_encode($json_languages);
								file_put_contents('include/db/languages.json', $json_new);
							}
						}
					}
					
					header("Location: ".$site_url.'admin/language');
					die();
				}
			}
			else if($admin_page=='donate')
			{
				$jsondataDonate = file_get_contents('include/db/donate.json');
				$jsondataDonate = json_decode($jsondataDonate, true);
				
				$jsondataCurrency = file_get_contents('include/db/currency.json');
				$jsondataCurrency = json_decode($jsondataCurrency,true);
				$currency = $jsondataCurrency[$jsondata['general']['currency']]['code'];
				
				if(!$jsondataDonate)
					$jsondataDonate = array();
				
				if(isset($_POST['submit']))
				{
					$new_link = array();
					$new_link['name'] = $_POST['download_server'];
					$new_link['list'] = array();
					
					array_push($jsondataDonate, $new_link);
					
					$json_new = json_encode($jsondataDonate);
					file_put_contents('include/db/donate.json', $json_new);
					
					header("Location: ".$site_url.'admin/donate');
					die();
				} else if(isset($_GET['del']))
				{
					unset($jsondataDonate[$_GET['del']]);
					
					$json_new = json_encode($jsondataDonate);
					file_put_contents('include/db/donate.json', $json_new);
					
					header("Location: ".$site_url.'admin/donate');
					die();
				}  else if(isset($_POST['submit_delete_price']))
				{
					unset($jsondataDonate[$_POST['id']]['list'][$_POST['price_id']]);
					
					$json_new = json_encode($jsondataDonate);
					file_put_contents('include/db/donate.json', $json_new);
					
					header("Location: ".$site_url.'admin/donate');
					die();
				} else if(isset($_POST['submit_price']))
				{
					$new_price = array();
					$new_price['price'] = $_POST['price'];
					$new_price['md'] = $_POST['md'];

					array_push($jsondataDonate[$_POST['id']]['list'], $new_price);
					
					$json_new = json_encode($jsondataDonate);
					file_put_contents('include/db/donate.json', $json_new);
					
					header("Location: ".$site_url.'admin/donate');
					die();
				}
			}
			else if($admin_page=='donatelist')
			{
				if(isset($_POST['yes']))
				{
					updateDonateStatus($_POST['id'], 1);
					addCoins($_POST['account'], $_POST['md']);
				} else if(isset($_POST['no']))
				{
					updateDonateStatus($_POST['id'], 2);
				}
				$jsondataDonate = get_donations();
			}
			else if($admin_page=='coins' && isset($_POST['account']))
			{
				if($_POST['account']==1)
					$account_id = getAccountIDbyName($_POST['name']);
				else
					$account_id = getAccountIDbyChar($_POST['name']);
				
				$added = 0;
				if($account_id)
				{
					if($_POST['type']==1)
						addCoins($account_id, $_POST['coins']);
					else
						addjCoins($account_id, $_POST['coins']);
					$added = 1;
				} else $added = 2;
			}
		}
		
		include 'include/functions/top10backup.php';
	}
	else
	{
		$web_admin = 0;
		if($page!='news' && $page!='read')
		{
			header("Location: ".$site_url);
			die();
		}
		$offline_date=getJsonSettings("day", "top10backup").'.'.getJsonSettings("month", "top10backup").'.'.getJsonSettings("year", "top10backup");
		$offline_year=getJsonSettings("year", "top10backup");
		$offline_players=getJsonSettings("players", "top10backup");
		$offline_guilds=getJsonSettings("guilds", "top10backup");
	}

	if(isset($_GET['api']) && isset($_GET['key']) && $_GET['api']=='metin2cms')
	{
		$apidata = file_get_contents('include/db/api.json');
		$apidata = json_decode($apidata,true);
		
		if($_GET['key']==$apidata['key'])
			die('ok');
		else
			die();
	}