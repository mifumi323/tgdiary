<?php // PHP4.23�d�l�Ȃ̂ŋ��o�[�W�����ł͐���ɓ��삵�Ȃ�

// TGdiary v1.3.1
//
//  TGWS�Ŏg���Ă�����L�v���O�����Ƃقړ����ł��B
//  �p�[�~�b�V�����́Adiary.php�{�̂�index.html��644�A
//  ���͑S��666�ł��B
//  �t�@�C�����A�b�v���[�h����Ȃ�΁A�A�b�v���[�h�f�B���N�g����
//  �p�[�~�b�V������777��707�Ȃǂɂ��Ă��������B
//  ver1.01�����diary.log�𓯍����Ă��܂���B
//  �ŏ��͋�̃t�@�C���ł��̂Ŋe���쐬���Ă��������B
//
// v1.3.1 �I�[�v���\�[�X��
// v1.03 PHP5�ɑΉ�(�x�����C�ɂ��Ȃ��Ȃ�1.02�ł�OK)
// v1.02 �ҏW��ʂŃW�������\���𐮗����ĕ\��
// v1.01 �ҏW�Ń^�O���g���Ȃ��o�O�C��
//
//  ��ҁF����(https://tgws.plus/ mifumi323@tgws.fromc.jp)


Initialize();
view();

function Initialize()	// ������
{
	global $setting;
	$setting = array(
		'this'	=> './diary.php',
		'viewmonth'	=> 31,
		'viewjunre'	=> 20,
		'viewfind'	=> 20,
		'viewrecent'	=> 7,
		'pass'	=> '',	//�����������Ȃ���ΒN�ł����L
		'week'	=> array('Sun','Mon','Tue','Wed','Thu','Fri','Sat'),
		'cookie'	=> 'TGDIARY',
		'diaryfile'	=> './diary.log',
		'junrefile'	=> './junre.log',
		'jsort'		=> true,	// �ҏW��ʂŃW�������\���𐮗����ĕ\��
		'htmlfile'	=> './diary.html',
		'menufile'	=> './diarymenu.html',
		'updir'		=> './up/',
	);
}

function view()	// �\��
{
	global $_REQUEST, $setting;
	if ($_REQUEST['admin'])	// �Ǘ��҃t�H�[��
	{
		if (AdminCheck()) Admin(); else Login();
	} else if ($_REQUEST['month'])	// ���ʂ̓��L
	{
		$title = floor($_REQUEST['month']/100+2000).'�N'.($_REQUEST['month']%100).'��';
		echo ViewList($title.'�̓��L', $title, $_REQUEST['log'], $setting['viewmonth'], 'CheckMonth', 'month='.$_REQUEST['month'].'&amp;');
	} else if ($_REQUEST['junre'])	// �W����������
	{
		$junre=stripslashes($_REQUEST['junre']);
		echo ViewList($junre.'�Ɋւ�����L', $junre, $_REQUEST['log'], $setting['viewjunre'], 'CheckJunre', 'junre='.urlencode($junre).'&amp;');
	} else if ($_REQUEST['find'])	// ���[�h����
	{
		$find=stripslashes($_REQUEST['find']);
		global $findstr;
		$findstr = explode(' ', $find);
		echo ViewList(htmlspecialchars($find).'���܂ޓ��L', htmlspecialchars($find), $_REQUEST['log'], $setting['viewfind'], 'CheckFind', 'find='.urlencode($find).'&amp;');
	} else if ($_REQUEST['date'])	// ���̓��̓��L
	{
		// ���ꂾ���͕\���A���S���Y�����Ⴄ�̂�
		$date = $_REQUEST['date'];
		if ($date!='someday')	// ���ʂ�
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
				$navi = '<a href="'.$setting['this'].'?date='.$buf[0].'">'.����.'</a>';
			}
			if ($next) {
				if ($prev) $navi .= ' | ';
				$buf = explode('<>', $next);
				$navi .= '<a href="'.$setting['this'].'?date='.$buf[0].'">'.�O��.'</a>';
			}
			if ($navi) $navi = "<p class=navi>$navi</p>";
			$diary = CreateDiaryPage($data);
			$buf = explode('<>', $data);
			list($sec,$min,$hour,$mday,$mon,$year,$wday) = localtime((integer)$buf[0]);
			$date = sprintf("%04d/%02d/%02d(%s)",$year+1900,$mon+1,$mday,$setting['week'][$wday]);
			echo CommonHeader($date.'�̓��L',$date).'<hr><dl>'.$diary.'</dl>'.$navi.CommonFooter();
		}else	// �����_���\��
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
	} else			// �ŐV�̓��L
	{
		echo ViewList('�ŐV�̓��L', '�ŐV', $_REQUEST['log'], $setting['viewrecent'], 'True', '');
	}
}

function ViewList($longtitle,$shorttitle,$start,$count,$callback,$query,$viewadmin=true)	// �����ɍ������L�����X�g�\��
{
	global $setting;
	$end = $start + $count;
	$fp = fopen($setting['diaryfile'],'rt');
	$ret = CommonHeader($longtitle, $shorttitle);
	if ($viewadmin && AdminCheck())
		$ret .= '<p><a href="'.$setting['this'].'?admin=enter">���O�C��</a></p>';
	$ret .= '<hr><dl>';
	$i = 0;
	while ($line = fgets($fp,8192))
	{
		if ($callback($line)) {
			if ($start <= $i) {
				if ($i < $end) {
					$ret .= CreateDiaryPage($line, $viewadmin);
				}else{
					$next = '<a href="'.$setting['this'].'?'.$query.'log='.$end.'">�Â�'.$count.'��</a>';
					break;
				}
			}
			$i++;
		}
	}
	if ($start) {
		$prev = $start - $count;
		$prev = '<a href="'.$setting['this'].'?'.$query.'log='.$prev.'">�V����'.$count.'��</a>';
	}
	$navi = $next?
		($prev?	"<p class=navi>$prev | $next</p>":	"<p class=navi>$next</p>"):
		($prev?	"<p class=navi>$prev</p>":		"");
	$ret .= '</dl>'.$navi.CommonFooter();
	fclose($fp);
	return $ret;
}

function CheckMonth($line)	// �����F��
{
	global $_GET;
	list($time,$sub,$text,$com,$junre) = explode('<>', $line);
	list($sec,$min,$hour,$mday,$mon,$year,$wday) = localtime((integer)$time);
	return ($year*100 + $mon+1) % 10000 == $_GET['month'];
}

function CheckJunre($line)	// �����F�J�e�S��
{
	global $_GET;
	list($time,$sub,$text,$com,$junre) = explode('<>', $line);
	$junres = explode(',', $junre);
	foreach ($junres as $_) {
		if ($_ == stripslashes($_GET['junre'])) return true;
	}
	return false;
}

function CheckFind($line)	// �����F��������
{
	global $findstr;
	foreach ($findstr as $f) {
		if (strstr($line,$f)===false) {
			return false;
		}
	}
	return true;
}

function True($line)	// �����F�Ȃ�
{
	return true;
}

function AdminCheck()	// �Ǘ��҃`�F�b�N
{
	global $setting, $_COOKIE;
	return $_COOKIE[$setting['cookie']] == $setting['pass'];
}

function Login()	// ���O�C��
{
	global $_SERVER, $_POST, $setting;
	if ($_POST['pass']!=$setting['pass'])
	{
		$query = htmlspecialchars(stripcslashes($_SERVER['QUERY_STRING']));
		echo CommonHeader('���O�C��','���O�C��');
		echo <<<EOM
<form action="$setting[this]" method="POST" style="text-align:center">
<input type=hidden name=admin value="login">
<input type=hidden name=query value="$query">
�p�X���[�h�F<input type=password name=pass size=8 value="">
<input type=submit value="���M����"><input type=reset value="���Z�b�g">
</form>
EOM;
		echo CommonFooter();
	} else
	{
		setcookie($setting['cookie'], $_POST['pass'], time()+31536000);
		header('Location: '.$setting['this'].'?'.$_POST['query']);
	}
}

function Logout()	// ���O�A�E�g
{
	global $setting;
	setcookie($setting['cookie'], '', time()-3600);
}

function Admin()	// �Ǘ����
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

function AdminFrame()	// �Ǘ���ʃt���[��
{
	global $setting;
	echo <<<EOM
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN">
<html><head><title>���L&gt;�Ǘ�</title></head>
<frameset rows="50%,*">
<frame src="$setting[this]?admin=top" name="form" title="�t�H�[��">
<frame src="$setting[htmlfile]" name="ctrl" title="����">
</frameset></html>
EOM;
}

function AdminTop()	// �Ǘ���ʃg�b�v
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
	echo CommonHeader('', '�Ǘ�', "<base target=ctrl>\n<style>\n<!--\nlabel { white-space:	 nowrap; }\n-->\n</style>");
	echo <<<EOM
<form action="$setting[this]" method="POST" enctype="multipart/form-data">
<input type=hidden name=admin value=regist>
<table>
<tr><td><b>�薼</b></td><td><input type=text name=sub size=76 value="">
<input type=submit value="���M����"> <input type=reset value="���Z�b�g"></td>
<td rowspan=3 valign=top nowrap>
<a href="$setting[htmlfile]">�ŐV�L��</a><br>
<a href="$setting[this]?admin=create">HTML����</a><br>
<a href="$setting[this]?admin=menu">���j���[�쐬</a><br>
<a href="$setting[this]?admin=logoff">���O�I�t</a>
</td></tr>
<tr><td nowrap><b>���b�Z�[�W</b><br><input type=checkbox name=tag value="on"> �^�O</td><td>
<textarea name=comment cols=73 rows=4 wrap=soft></textarea></td></tr>
<tr><td><b>�t�@�C��</b></td><td><input type=file name=upfile size=93 value=""></td></tr>
<tr><td colspan=3><b>�W������</b>
$junres<br><input type=text name="junre[]" size=63 value="" title="�R���}�ŋ�؂�ׂ�">
</td></tr>
</table>
</form>
EOM;
	echo CommonFooter();
}

function Regist()	// ���L��������
{
	global $setting, $_POST, $_FILES;
	// �f�[�^���`���`�F�b�N
	$subtitle = htmlspecialchars(stripslashes($_POST['sub']));
	if ($subtitle=='') $subtitle = '����';
	if (($comment=$_POST['comment'])=='') die('�{���������B');
	$comment = stripslashes($comment);
	if ($_POST['tag']!='on') $comment = htmlspecialchars($comment);
	$comment = str_replace(array("\n","\r"),'<br>',str_replace("\r\n",'<br>',$comment));
	$junre = stripslashes(implode(',', $_POST['junre']));
	$time = time();
	// �t�@�C���A�b�v���[�h
	if ($_FILES['upfile']['name']) {
		$ext = strrchr($_FILES['upfile']['name'],'.');
		$upfile = $setting['updir'].$time.$ext;
		move_uploaded_file($_FILES['upfile']['tmp_name'], $upfile);
		chmod($upfile, 0666);
	}
	// �������ݏ���
	$fp = fopen($setting['diaryfile'],'rt');
	$tmpfname = tempnam ('/tmp', 'dat');
	$tp = fopen($tmpfname, 'wt');
	$hp = fopen($setting['htmlfile'], 'wt');
	$line = "$time<>$subtitle<>$comment<><>$junre<>$ext<>\n";
	$i = 0;	$count = $setting['viewrecent'];
	fwrite($hp, CommonHeader('�ŐV�̓��L', '�ŐV').'<hr><dl>');
	do {
		fwrite($tp, $line);
		ParseMonth($line);
		ParseJunre($line);
		if ($i++<$count) fwrite($hp, CreateDiaryPage($line, false));
	} while ($line = fgets($fp,8192));
	fwrite($hp, '</dl>'.($i>$count?'<p class=navi><a href="'.$setting['this'].'?log='.$count.'">�Â�'.$count.'��</a></p>':'').CommonFooter());
	fclose($fp);
	fclose($tp);
	copy($tmpfname, $setting['diaryfile']);
	unlink($tmpfname);
	fclose($hp);
	CreateMenu();
}

function CreateHTML()	// HTML�쐬
{
	global $setting, $_POST, $_FILES;
	// �������ݏ���
	$fp = fopen($setting['diaryfile'],'rt');
	$hp = fopen($setting['htmlfile'], 'wt');
	$i = 0;	$count = $setting['viewrecent'];
	fwrite($hp, CommonHeader('�ŐV�̓��L', '�ŐV').'<hr><dl>');
	while ($line = fgets($fp,8192)) {
		if ($i++>=$count) break;
		fwrite($hp, CreateDiaryPage($line, false));
	}
	fwrite($hp, '</dl>'.($i>$count?'<p class=navi><a href="'.$setting['this'].'?log='.$count.'">�Â�'.$count.'��</a></p>':'').CommonFooter());
	fclose($fp);
	fclose($hp);
}

function CreateMenu()	// ���j���[�쐬
{
	global $setting, $monthlist, $junrelist;
	// �������ł��ĂȂ�������܂�����
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
	// �������炪�{��
	$mp = fopen($setting['menufile'], 'wt');
	fwrite($mp, CommonHeader('Diary', '�ꗗ', <<<EOM
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
<li><a href="$setting[this]">�ŐV�̓��L</a></li>
<li><a href="$setting[this]?date=someday">���̂Ƃ��̂킵</a></li>
<li><a href="javascript:onClick=display('past');" target="_self">�ߋ����O</a><ul id=past>

EOM
	);
	foreach ($monthlist as $month) fwrite($mp, '<li><a href="'.$setting['this'].'?month='.$month.'">'.floor(2000+$month/100).'�N'.($month%100).'��</a></li>'."\n");
	fwrite($mp, <<<EOM
</ul></li>
<li><a href="javascript:onClick=display('cat');" target="_self">�J�e�S��</a><ul id=cat>

EOM
	);
	foreach ($junrelist as $junre) fwrite($mp, '<li><a href="'.$setting['this'].'?junre='.urlencode($junre).'">'.$junre.'</a></li>'."\n");
	fwrite($mp, <<<EOM
</ul>
<li>����<br>
<form action="$setting[this]" METHOD=GET style="display:inline;">
<input type=text name=find value="">
<input type=submit value="����">
</form>
</ul>
<script language="javascript" type="text/javascript">
<!--
document.write('<p class=info>�N���b�N�Ń��X�g���J���܂��B</p>');
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

function EditForm()	// ���L�ҏW�t�H�[��
{
	global $setting, $_GET;
	// �ҏW��T����
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
	// �F�X�ϊ�
	$diary = CreateDiaryPage($data, false);
	$date = TimeToShortDate($otime);
	$osub = htmlspecialchars($osub);
	$otext = htmlspecialchars(str_replace('<br>',"\n",$otext));
	$ocom = htmlspecialchars($ocom);
	$upfile = $oext?$otime.$oext:'�Ȃ�';
	// �W�������擾
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
	// �t�H�[��
	echo CommonHeader($date.'�̕ҏW', '�Ǘ�', "<style>\n<!--\nlabel { white-space:	 nowrap; }\n-->\n</style>");
	echo <<<EOM
<form action="$setting[this]" method="POST">
<input type=hidden name=admin value=modify>
<input type=hidden name=date value=$otime>
<input type=hidden name=upfile value="$oext">
<table>
<tr><td><b>�薼</b></td><td><input type=text name=sub size=76 value="$osub">
<input type=submit value="���M����"> <input type=reset value="���Z�b�g"></td></tr>
<tr><td nowrap><b>���b�Z�[�W</b><br>�^�O�L��</td><td>
<textarea name=comment cols=73 rows=4 wrap=soft>$otext</textarea></td></tr>
<tr><td><b>�t�@�C��</b></td><td>$upfile</td></tr>
<tr><td><b>�R�����g</b></td><td><input type=text name=com size=76 value="$ocom">
<tr><td colspan=2><b>�W������</b>
$junres<br><input type=text name="junre[]" size=63 value="" title="�R���}�ŋ�؂�ׂ�">
</td></tr>
</table>
</form>
<hr><dl>
$diary
</dl>
EOM;
	echo CommonFooter();
}

function Modify()	// ���L�ҏW���f
{
	global $setting, $_POST, $_FILES;
	// �f�[�^���`���`�F�b�N
	$time = $_POST['date'];
	$del = ($comment=$_POST['comment'])=='';
	if (!$del) {
		$subtitle = htmlspecialchars(stripslashes($_POST['sub']));
		if ($subtitle=='') $subtitle = '����';
		$comment = stripslashes($comment);
		//if ($_POST['tag']!='on') $comment = htmlspecialchars($comment);
		$comment = str_replace(array("\n","\r"),'<br>',str_replace("\r\n",'<br>',$comment));
		$subcom = $_POST['com'];
		$junre = stripslashes(implode(',', $_POST['junre']));
		$ext = $_POST['upfile'];
	}
	// �������ݏ���
	$fp = fopen($setting['diaryfile'],'rt');
	$tmpfname = tempnam ('/tmp', 'dat');
	$tp = fopen($tmpfname, 'wt');
	$hp = fopen($setting['htmlfile'], 'wt');
	$i = 0;	$count = $setting['viewrecent'];
	fwrite($hp, CommonHeader('�ŐV�̓��L', '�ŐV').'<hr><dl>');
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
	fwrite($hp, '</dl>'.($i>$count?'<p class=navi><a href="'.$setting['this'].'?log='.$count.'">�Â�'.$count.'��</a></p>':'').CommonFooter());
	fclose($fp);
	fclose($tp);
	copy($tmpfname, $setting['diaryfile']);
	unlink($tmpfname);
	fclose($hp);
	CreateMenu();
}

function ParseMonth($line)	// ���𔲂��o���Ĕz��ɕۑ�
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

function ParseJunre($line)	// �W�������𔲂��o���Ĕz��ɕۑ�
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

function CreateDiaryPage($line,$viewadmin=true)	// ����̓��̓��L��HTML�𐶐�
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
		$junre .= ' [<a href="'.$setting['this'].'?admin=edit&amp;date='.$time.'">�ҏW</a>]';
	}
	$file = strstr($ext,'.') ? "<a href=\"up/$time$ext\">$time$ext</a><br>" : '';
	return	'<dt>'.$date.' <font color="#ff0000">'.$sub.'</font></dt>'.
		'<dd>'.$text.'<br><br>'.$file.'<small>'.$com.$junre.'</small><hr>';
}

function CommonHeader($longtitle='',$shorttitle='',$header='')	// ���ʃw�b�_
{
	if ($longtitle) $longtitle = "\n<h1>$longtitle</h1>";
	if ($shorttitle) $shorttitle = "&gt;$shorttitle";
	return <<<EOM
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html lang="ja"><head>
<meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">
<META NAME="ROBOTS" CONTENT="NOINDEX">
<title>���L$shorttitle</title>
$header</head><body class=wide>$longtitle
EOM;
}

function CommonFooter()	// ���ʃt�b�^
{
	return '</body></html>';
}

function TimeToDate($time)	// ���t��UNIX�W�������當�����
{
	global $setting;
	list($sec,$min,$hour,$mday,$mon,$year,$wday) = localtime((integer)$time);
	return sprintf("%04d/%02d/%02d(%s) %02d:%02d:%02d",
			$year+1900,$mon+1,$mday,$setting['week'][$wday],$hour,$min,$sec);
}

function TimeToShortDate($time)	// ���t��UNIX�W�������當�����(�����\���Ȃ�)
{
	global $setting;
	list($sec,$min,$hour,$mday,$mon,$year,$wday) = localtime((integer)$time);
	return sprintf("%04d/%02d/%02d(%s)",
			$year+1900,$mon+1,$mday,$setting['week'][$wday]);
}

function make_seed()	// �����̎�
{
	list($usec, $sec) = explode(' ', microtime());
	return (float) $sec + ((float) $usec * 100000);
}

?>