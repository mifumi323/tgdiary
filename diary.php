<?php // PHP4.23仕様なので旧バージョンでは正常に動作しない

// TGdiary v1.3.1
//
//  TGWSで使っている日記プログラムとほぼ同じです。
//  パーミッションは、diary.php本体とindex.htmlは644、
//  他は全部666です。
//  ファイルをアップロードするならば、アップロードディレクトリの
//  パーミッションを777や707などにしてください。
//  ver1.01からはdiary.logを同梱していません。
//  最初は空のファイルですので各自作成してください。
//
// v1.3.1 オープンソース化
// v1.03 PHP5に対応(警告を気にしないなら1.02でもOK)
// v1.02 編集画面でジャンル表示を整理して表示
// v1.01 編集でタグが使えないバグ修正
//
//  作者：美文(https://tgws.plus/ mifumi323@tgws.fromc.jp)


Initialize();
view();

function Initialize()	// 初期化
{
	global $setting;
	$setting = array(
		'this'	=> './diary.php',
		'viewmonth'	=> 31,
		'viewjunre'	=> 20,
		'viewfind'	=> 20,
		'viewrecent'	=> 7,
		'pass'	=> '',	//←何も書かなければ誰でも日記
		'week'	=> array('Sun','Mon','Tue','Wed','Thu','Fri','Sat'),
		'cookie'	=> 'TGDIARY',
		'diaryfile'	=> './diary.log',
		'junrefile'	=> './junre.log',
		'jsort'		=> true,	// 編集画面でジャンル表示を整理して表示
		'htmlfile'	=> './diary.html',
		'menufile'	=> './diarymenu.html',
		'updir'		=> './up/',
	);
}

function view()	// 表示
{
	global $_REQUEST, $setting;
	if ($_REQUEST['admin'])	// 管理者フォーム
	{
		if (AdminCheck()) Admin(); else Login();
	} else if ($_REQUEST['month'])	// 月別の日記
	{
		$title = floor($_REQUEST['month']/100+2000).'年'.($_REQUEST['month']%100).'月';
		echo ViewList($title.'の日記', $title, $_REQUEST['log'], $setting['viewmonth'], 'CheckMonth', 'month='.$_REQUEST['month'].'&amp;');
	} else if ($_REQUEST['junre'])	// ジャンル検索
	{
		$junre=stripslashes($_REQUEST['junre']);
		echo ViewList($junre.'に関する日記', $junre, $_REQUEST['log'], $setting['viewjunre'], 'CheckJunre', 'junre='.urlencode($junre).'&amp;');
	} else if ($_REQUEST['find'])	// ワード検索
	{
		$find=stripslashes($_REQUEST['find']);
		global $findstr;
		$findstr = explode(' ', $find);
		echo ViewList(htmlspecialchars($find).'を含む日記', htmlspecialchars($find), $_REQUEST['log'], $setting['viewfind'], 'CheckFind', 'find='.urlencode($find).'&amp;');
	} else if ($_REQUEST['date'])	// その日の日記
	{
		// これだけは表示アルゴリズムが違うのだ
		$date = $_REQUEST['date'];
		if ($date!='someday')	// 普通に
		{
			$fp = fopen($setting['diaryfile'],'rt');
			while ($line = fgets($fp,8192)) {
				list($time) = explode('<>', $line);
				if ($time == $date) {
					$data = $line;
					$next = fgets($fp,8192);
					break;
				}
				$prev = $line;
			}
			fclose($fp);
			if ($prev) {
				$buf = explode('<>', $prev);
				$navi = '<a href="'.$setting['this'].'?date='.$buf[0].'">'.翌日.'</a>';
			}
			if ($next) {
				if ($prev) $navi .= ' | ';
				$buf = explode('<>', $next);
				$navi .= '<a href="'.$setting['this'].'?date='.$buf[0].'">'.前日.'</a>';
			}
			if ($navi) $navi = "<p class=navi>$navi</p>";
			$diary = CreateDiaryPage($data);
			$buf = explode('<>', $data);
			list($sec,$min,$hour,$mday,$mon,$year,$wday) = localtime((integer)$buf[0]);
			$date = sprintf("%04d/%02d/%02d(%s)",$year+1900,$mon+1,$mday,$setting['week'][$wday]);
			echo CommonHeader($date.'の日記',$date).'<hr><dl>'.$diary.'</dl>'.$navi.CommonFooter();
		}else	// ランダム表示
		{
			mt_srand(make_seed());
			$no = 0;
			$fp = fopen($setting['diaryfile'],'rt');
			while ($line = fgets($fp,8192)) {
				if (mt_rand(0,$no)==0) $data = $line;
				$no++;
			}
			fclose($fp);
			$buf = explode('<>', $data);
			header('Location: '.$setting['this'].'?date='.$buf[0]);
		}
	} else			// 最新の日記
	{
		echo ViewList('最新の日記', '最新', $_REQUEST['log'], $setting['viewrecent'], 'True', '');
	}
}

function ViewList($longtitle,$shorttitle,$start,$count,$callback,$query,$viewadmin=true)	// 条件に合う日記をリスト表示
{
	global $setting;
	$end = $start + $count;
	$fp = fopen($setting['diaryfile'],'rt');
	$ret = CommonHeader($longtitle, $shorttitle);
	if ($viewadmin && AdminCheck())
		$ret .= '<p><a href="'.$setting['this'].'?admin=enter">ログイン</a></p>';
	$ret .= '<hr><dl>';
	$i = 0;
	while ($line = fgets($fp,8192))
	{
		if ($callback($line)) {
			if ($start <= $i) {
				if ($i < $end) {
					$ret .= CreateDiaryPage($line, $viewadmin);
				}else{
					$next = '<a href="'.$setting['this'].'?'.$query.'log='.$end.'">古い'.$count.'日</a>';
					break;
				}
			}
			$i++;
		}
	}
	if ($start) {
		$prev = $start - $count;
		$prev = '<a href="'.$setting['this'].'?'.$query.'log='.$prev.'">新しい'.$count.'日</a>';
	}
	$navi = $next?
		($prev?	"<p class=navi>$prev | $next</p>":	"<p class=navi>$next</p>"):
		($prev?	"<p class=navi>$prev</p>":		"");
	$ret .= '</dl>'.$navi.CommonFooter();
	fclose($fp);
	return $ret;
}

function CheckMonth($line)	// 条件：月
{
	global $_GET;
	list($time,$sub,$text,$com,$junre) = explode('<>', $line);
	list($sec,$min,$hour,$mday,$mon,$year,$wday) = localtime((integer)$time);
	return ($year*100 + $mon+1) % 10000 == $_GET['month'];
}

function CheckJunre($line)	// 条件：カテゴリ
{
	global $_GET;
	list($time,$sub,$text,$com,$junre) = explode('<>', $line);
	$junres = explode(',', $junre);
	foreach ($junres as $_) {
		if ($_ == stripslashes($_GET['junre'])) return true;
	}
	return false;
}

function CheckFind($line)	// 条件：検索結果
{
	global $findstr;
	foreach ($findstr as $f) {
		if (strstr($line,$f)===false) {
			return false;
		}
	}
	return true;
}

function True($line)	// 条件：なし
{
	return true;
}

function AdminCheck()	// 管理者チェック
{
	global $setting, $_COOKIE;
	return $_COOKIE[$setting['cookie']] == $setting['pass'];
}

function Login()	// ログイン
{
	global $_SERVER, $_POST, $setting;
	if ($_POST['pass']!=$setting['pass'])
	{
		$query = htmlspecialchars(stripcslashes($_SERVER['QUERY_STRING']));
		echo CommonHeader('ログイン','ログイン');
		echo <<<EOM
<form action="$setting[this]" method="POST" style="text-align:center">
<input type=hidden name=admin value="login">
<input type=hidden name=query value="$query">
パスワード：<input type=password name=pass size=8 value="">
<input type=submit value="送信する"><input type=reset value="リセット">
</form>
EOM;
		echo CommonFooter();
	} else
	{
		setcookie($setting['cookie'], $_POST['pass'], time()+31536000);
		header('Location: '.$setting['this'].'?'.$_POST['query']);
	}
}

function Logout()	// ログアウト
{
	global $setting;
	setcookie($setting['cookie'], '', time()-3600);
}

function Admin()	// 管理画面
{
	global $setting, $_REQUEST;
	$mode = $_REQUEST['admin'];
	if ($mode == 'enter')
	{
		AdminFrame();
	} else if ($mode == 'top')
	{
		AdminTop();
	} else if ($mode == 'regist')
	{
		Regist();
		header('Location: '.$setting['htmlfile']);
	} else if ($mode == 'create')
	{
		CreateHTML();
		header('Location: '.$setting['htmlfile']);
	} else if ($mode == 'menu')
	{
		CreateMenu();
		header('Location: '.$setting['menufile']);
	} else if ($mode == 'edit')
	{
		EditForm();
	} else if ($mode == 'modify')
	{
		Modify();
		header('Location: '.$setting['this'].'?date='.$_REQUEST['date']);
	} else if ($mode == 'logoff')
	{
		Logout();
		header('Location: '.$setting['this']);
	} else
	{
		$_REQUEST['admin'] = false;
		view();
	}
}

function AdminFrame()	// 管理画面フレーム
{
	global $setting;
	echo <<<EOM
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN">
<html><head><title>日記&gt;管理</title></head>
<frameset rows="50%,*">
<frame src="$setting[this]?admin=top" name="form" title="フォーム">
<frame src="$setting[htmlfile]" name="ctrl" title="操作">
</frameset></html>
EOM;
}

function AdminTop()	// 管理画面トップ
{
	global $setting;
	$junres = '';
	$fp = fopen($setting['junrefile'],"rt");
	$junre = explode(',',fgets($fp,8192));
	fclose($fp);
	if ($setting['jsort']) {
		sort($junre);
		reset($junre);
	}
	foreach ($junre as $j){
		$j = str_replace(array("\r","\n"), '', $j);
		$junres .= "<label for=\"$j\"><input type=checkbox name=\"junre[]\" id=\"$j\" value=\"$j\">$j</label>\n";
	}
	echo CommonHeader('', '管理', "<base target=ctrl>\n<style>\n<!--\nlabel { white-space:	 nowrap; }\n-->\n</style>");
	echo <<<EOM
<form action="$setting[this]" method="POST" enctype="multipart/form-data">
<input type=hidden name=admin value=regist>
<table>
<tr><td><b>題名</b></td><td><input type=text name=sub size=76 value="">
<input type=submit value="送信する"> <input type=reset value="リセット"></td>
<td rowspan=3 valign=top nowrap>
<a href="$setting[htmlfile]">最新記事</a><br>
<a href="$setting[this]?admin=create">HTML生成</a><br>
<a href="$setting[this]?admin=menu">メニュー作成</a><br>
<a href="$setting[this]?admin=logoff">ログオフ</a>
</td></tr>
<tr><td nowrap><b>メッセージ</b><br><input type=checkbox name=tag value="on"> タグ</td><td>
<textarea name=comment cols=73 rows=4 wrap=soft></textarea></td></tr>
<tr><td><b>ファイル</b></td><td><input type=file name=upfile size=93 value=""></td></tr>
<tr><td colspan=3><b>ジャンル</b>
$junres<br><input type=text name="junre[]" size=63 value="" title="コンマで区切るべし">
</td></tr>
</table>
</form>
EOM;
	echo CommonFooter();
}

function Regist()	// 日記書きこみ
{
	global $setting, $_POST, $_FILES;
	// データ整形＆チェック
	$subtitle = htmlspecialchars(stripslashes($_POST['sub']));
	if ($subtitle=='') $subtitle = '無題';
	if (($comment=$_POST['comment'])=='') die('本文を書け。');
	$comment = stripslashes($comment);
	if ($_POST['tag']!='on') $comment = htmlspecialchars($comment);
	$comment = str_replace(array("\n","\r"),'<br>',str_replace("\r\n",'<br>',$comment));
	$junre = stripslashes(implode(',', $_POST['junre']));
	$time = time();
	// ファイルアップロード
	if ($_FILES['upfile']['name']) {
		$ext = strrchr($_FILES['upfile']['name'],'.');
		$upfile = $setting['updir'].$time.$ext;
		move_uploaded_file($_FILES['upfile']['tmp_name'], $upfile);
		chmod($upfile, 0666);
	}
	// 書き込み処理
	$fp = fopen($setting['diaryfile'],'rt');
	$tmpfname = tempnam ('/tmp', 'dat');
	$tp = fopen($tmpfname, 'wt');
	$hp = fopen($setting['htmlfile'], 'wt');
	$line = "$time<>$subtitle<>$comment<><>$junre<>$ext<>\n";
	$i = 0;	$count = $setting['viewrecent'];
	fwrite($hp, CommonHeader('最新の日記', '最新').'<hr><dl>');
	do {
		fwrite($tp, $line);
		ParseMonth($line);
		ParseJunre($line);
		if ($i++<$count) fwrite($hp, CreateDiaryPage($line, false));
	} while ($line = fgets($fp,8192));
	fwrite($hp, '</dl>'.($i>$count?'<p class=navi><a href="'.$setting['this'].'?log='.$count.'">古い'.$count.'日</a></p>':'').CommonFooter());
	fclose($fp);
	fclose($tp);
	copy($tmpfname, $setting['diaryfile']);
	unlink($tmpfname);
	fclose($hp);
	CreateMenu();
}

function CreateHTML()	// HTML作成
{
	global $setting, $_POST, $_FILES;
	// 書き込み処理
	$fp = fopen($setting['diaryfile'],'rt');
	$hp = fopen($setting['htmlfile'], 'wt');
	$i = 0;	$count = $setting['viewrecent'];
	fwrite($hp, CommonHeader('最新の日記', '最新').'<hr><dl>');
	while ($line = fgets($fp,8192)) {
		if ($i++>=$count) break;
		fwrite($hp, CreateDiaryPage($line, false));
	}
	fwrite($hp, '</dl>'.($i>$count?'<p class=navi><a href="'.$setting['this'].'?log='.$count.'">古い'.$count.'日</a></p>':'').CommonFooter());
	fclose($fp);
	fclose($hp);
}

function CreateMenu()	// メニュー作成
{
	global $setting, $monthlist, $junrelist;
	// 準備ができてなかったらまず準備
	if (!is_array($monthlist) || !is_array($junrelist))
	{
		$readmonth = !is_array($monthlist);
		$readjunre = !is_array($junrelist);
		$fp = fopen($setting['diaryfile'],'rt');
		while ($line = fgets($fp,8192))
		{
			if ($readmonth) ParseMonth($line);
			if ($readjunre) ParseJunre($line);
		}
		fclose($fp);
	}
	// ここからが本番
	$mp = fopen($setting['menufile'], 'wt');
	fwrite($mp, CommonHeader('Diary', '一覧', <<<EOM
<link rel="stylesheet" type="text/css" href="../menu.css"><base target="diary">
<script language="javascript" type="text/javascript">
<!--
function display(id) {
	if (document.all) s = document.all[id].style;
	else if (document.getElementById) s = document.getElementById(id).style;
	else s = document.all[id].style;
	if (s.display!='none') s.display = 'none';
	else s.display = 'block';
}
//-->
</script>
EOM
	));
	fwrite($mp, <<<EOM
<ul>
<li><a href="$setting[this]">最新の日記</a></li>
<li><a href="$setting[this]?date=someday">あのときのわし</a></li>
<li><a href="javascript:onClick=display('past');" target="_self">過去ログ</a><ul id=past>

EOM
	);
	foreach ($monthlist as $month) fwrite($mp, '<li><a href="'.$setting['this'].'?month='.$month.'">'.floor(2000+$month/100).'年'.($month%100).'月</a></li>'."\n");
	fwrite($mp, <<<EOM
</ul></li>
<li><a href="javascript:onClick=display('cat');" target="_self">カテゴリ</a><ul id=cat>

EOM
	);
	foreach ($junrelist as $junre) fwrite($mp, '<li><a href="'.$setting['this'].'?junre='.urlencode($junre).'">'.$junre.'</a></li>'."\n");
	fwrite($mp, <<<EOM
</ul>
<li>検索<br>
<form action="$setting[this]" METHOD=GET style="display:inline;">
<input type=text name=find value="">
<input type=submit value="検索">
</form>
</ul>
<script language="javascript" type="text/javascript">
<!--
document.write('<p class=info>クリックでリストを開きます。</p>');
display('past'); display('cat');
//-->
</script>
EOM
	);
	fwrite($mp, CommonFooter());
	fclose($mp);
	$jp = fopen($setting['junrefile'], 'wt');
	fwrite($jp, implode(',',$junrelist));
	fclose($jp);
}

function EditForm()	// 日記編集フォーム
{
	global $setting, $_GET;
	// 編集先探すぜ
	$date = $_GET['date'];
	$fp = fopen($setting['diaryfile'],'rt');
	while ($line = fgets($fp,8192)) {
		list($otime,$osub,$otext,$ocom,$ojunre,$oext) = explode('<>', $line);
		if ($otime == $date) {
			$data = $line;
			break;
		}
	}
	fclose($fp);
	// 色々変換
	$diary = CreateDiaryPage($data, false);
	$date = TimeToShortDate($otime);
	$osub = htmlspecialchars($osub);
	$otext = htmlspecialchars(str_replace('<br>',"\n",$otext));
	$ocom = htmlspecialchars($ocom);
	$upfile = $oext?$otime.$oext:'なし';
	// ジャンル取得
	$ojunres = explode(',',$ojunre);
	$junres = '';
	$jp = fopen($setting['junrefile'],"rt");
	$junre = explode(',',fgets($jp,8192));
	fclose($jp);
	if ($setting['jsort']) {
		sort($junre);
		reset($junre);
	}
	foreach ($junre as $j){
		$j = str_replace(array("\r","\n"), '', $j);
		$check = in_array($j, $ojunres)?' checked':'';
		$junres .= "<label for=\"$j\"><input type=checkbox name=\"junre[]\" id=\"$j\" value=\"$j\"$check>$j</label>\n";
	}
	// フォーム
	echo CommonHeader($date.'の編集', '管理', "<style>\n<!--\nlabel { white-space:	 nowrap; }\n-->\n</style>");
	echo <<<EOM
<form action="$setting[this]" method="POST">
<input type=hidden name=admin value=modify>
<input type=hidden name=date value=$otime>
<input type=hidden name=upfile value="$oext">
<table>
<tr><td><b>題名</b></td><td><input type=text name=sub size=76 value="$osub">
<input type=submit value="送信する"> <input type=reset value="リセット"></td></tr>
<tr><td nowrap><b>メッセージ</b><br>タグ有効</td><td>
<textarea name=comment cols=73 rows=4 wrap=soft>$otext</textarea></td></tr>
<tr><td><b>ファイル</b></td><td>$upfile</td></tr>
<tr><td><b>コメント</b></td><td><input type=text name=com size=76 value="$ocom">
<tr><td colspan=2><b>ジャンル</b>
$junres<br><input type=text name="junre[]" size=63 value="" title="コンマで区切るべし">
</td></tr>
</table>
</form>
<hr><dl>
$diary
</dl>
EOM;
	echo CommonFooter();
}

function Modify()	// 日記編集反映
{
	global $setting, $_POST, $_FILES;
	// データ整形＆チェック
	$time = $_POST['date'];
	$del = ($comment=$_POST['comment'])=='';
	if (!$del) {
		$subtitle = htmlspecialchars(stripslashes($_POST['sub']));
		if ($subtitle=='') $subtitle = '無題';
		$comment = stripslashes($comment);
		//if ($_POST['tag']!='on') $comment = htmlspecialchars($comment);
		$comment = str_replace(array("\n","\r"),'<br>',str_replace("\r\n",'<br>',$comment));
		$subcom = $_POST['com'];
		$junre = stripslashes(implode(',', $_POST['junre']));
		$ext = $_POST['upfile'];
	}
	// 書き込み処理
	$fp = fopen($setting['diaryfile'],'rt');
	$tmpfname = tempnam ('/tmp', 'dat');
	$tp = fopen($tmpfname, 'wt');
	$hp = fopen($setting['htmlfile'], 'wt');
	$i = 0;	$count = $setting['viewrecent'];
	fwrite($hp, CommonHeader('最新の日記', '最新').'<hr><dl>');
	while ($line = fgets($fp,8192)) {
		list($t) = explode('<>', $line);
		if ($t==$time) {
			if ($del) continue;
			$line="$time<>$subtitle<>$comment<>$subcom<>$junre<>$ext<>\n";
		}
		fwrite($tp, $line);
		ParseMonth($line);
		ParseJunre($line);
		if ($i++<$count) fwrite($hp, CreateDiaryPage($line, false));
	}
	fwrite($hp, '</dl>'.($i>$count?'<p class=navi><a href="'.$setting['this'].'?log='.$count.'">古い'.$count.'日</a></p>':'').CommonFooter());
	fclose($fp);
	fclose($tp);
	copy($tmpfname, $setting['diaryfile']);
	unlink($tmpfname);
	fclose($hp);
	CreateMenu();
}

function ParseMonth($line)	// 月を抜き出して配列に保存
{
	global $monthlist;
	if (!is_array($monthlist)) $monthlist = array();
	list($time,$sub,$text,$com,$junre,$ext) = explode('<>', $line);
	list($sec,$min,$hour,$mday,$mon,$year,$wday) = localtime((integer)$time);
	$month = sprintf("%02d%02d",$year-100,$mon+1);
	$flag = TRUE;
	foreach ($monthlist as $m)
	{
		if ($m == $month) { $flag = FALSE; break; }
	}
	if ($flag) array_push($monthlist, $month);
}

function ParseJunre($line)	// ジャンルを抜き出して配列に保存
{
	global $junrelist;
	if (!is_array($junrelist)) $junrelist = array();
	list($time,$sub,$text,$com,$junre,$ext) = explode('<>', $line);
	$junres = explode(',', $junre);
	foreach ($junres as $j)
	{
		if ($j!='')
		{
			$flag = TRUE;
			foreach ($junrelist as $jl)
			{
				if ($j == $jl) { $flag = FALSE; break; }
			}
			if ($flag) array_push($junrelist, $j);
		}
	}
}

function CreateDiaryPage($line,$viewadmin=true)	// 特定の日の日記のHTMLを生成
{
	global $setting;
	list($time,$sub,$text,$com,$junre,$ext) = explode('<>', $line);
	$date = '<a href="'.$setting['this'].'?date='.$time.'">'.TimeToDate($time).'</a>';
	if ($com != '') { $com = "<i>$com</i><br>"; }
	$junres = explode(',', $junre);
	$junre = '';
	foreach ($junres as $_)
	{
		if ($_ != '')
			$junre .= ' [<a href="'.$setting['this'].'?junre='.urlencode($_).'">'.$_.'</a>]';
	}
	if ($viewadmin && AdminCheck()) {
		$junre .= ' [<a href="'.$setting['this'].'?admin=edit&amp;date='.$time.'">編集</a>]';
	}
	$file = strstr($ext,'.') ? "<a href=\"up/$time$ext\">$time$ext</a><br>" : '';
	return	'<dt>'.$date.' <font color="#ff0000">'.$sub.'</font></dt>'.
		'<dd>'.$text.'<br><br>'.$file.'<small>'.$com.$junre.'</small><hr>';
}

function CommonHeader($longtitle='',$shorttitle='',$header='')	// 共通ヘッダ
{
	if ($longtitle) $longtitle = "\n<h1>$longtitle</h1>";
	if ($shorttitle) $shorttitle = "&gt;$shorttitle";
	return <<<EOM
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html lang="ja"><head>
<meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">
<META NAME="ROBOTS" CONTENT="NOINDEX">
<title>日記$shorttitle</title>
$header</head><body class=wide>$longtitle
EOM;
}

function CommonFooter()	// 共通フッタ
{
	return '</body></html>';
}

function TimeToDate($time)	// 日付をUNIX標準時から文字列へ
{
	global $setting;
	list($sec,$min,$hour,$mday,$mon,$year,$wday) = localtime((integer)$time);
	return sprintf("%04d/%02d/%02d(%s) %02d:%02d:%02d",
			$year+1900,$mon+1,$mday,$setting['week'][$wday],$hour,$min,$sec);
}

function TimeToShortDate($time)	// 日付をUNIX標準時から文字列へ(時刻表示なし)
{
	global $setting;
	list($sec,$min,$hour,$mday,$mon,$year,$wday) = localtime((integer)$time);
	return sprintf("%04d/%02d/%02d(%s)",
			$year+1900,$mon+1,$mday,$setting['week'][$wday]);
}

function make_seed()	// 乱数の種
{
	list($usec, $sec) = explode(' ', microtime());
	return (float) $sec + ((float) $usec * 100000);
}

?>