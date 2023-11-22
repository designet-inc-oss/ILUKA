<?php

/*
 * ILUKA
 *
 * Copyright (C) 2006,2007 DesigNET, INC.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

/***********************************************************
 * バーチャルサーバ追加画面 
 *
 * $RCSfile$
 * $Revision$
 * $Date$
 **********************************************************/
include("../initial");
include_once("lib/dglibcommon");
include_once("lib/dglibpage");
include_once("lib/dglibsess");
include_once("lib/dglibiluka");

/********************************************************
各ページ毎の設定
*********************************************************/

define("OPERATION", "Add virtual_server");
define("TMPLFILE", "iluka_lb_vsadd.tmpl");

/***********************************************************
 * make_filedir
 *
 * バーチャルサーバ設定ファイル及びディレクトリの作成
 *
 * [引数]
 *       $tmplfile            IP_PORT.conf.tmplのパス
 *       $post                入力された値
 * [返り値]
 *       TRUE                 正常
 *       FALSE                異常
 **********************************************************/
function make_filedir ($tmplfile, $post)
{

    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;

    $dirs   = $web_conf["iluka"]["keepalivedbasedir"]."virtual_server/";
    $iport  = $post["ipaddress"]."_".$post["port"]."_".$post["protocol"];
    $vsfile = "$dirs$iport".'.conf';
    $vsdir  = "$dirs$iport";
    $rslist  = "$dirs$iport"."/real_server.conf";
    $sorry  = $post["sorry_server_ipaddress"].' '.$post["sorry_server_port"];

    $lb_algo = array('rr', 'wrr', 'lc', 'wlc', 'lblc', 'sh', 'dh');
    $lb_kind = array('NAT', 'DR', 'TUN');
    $protocol = array('TCP', 'UDP');

    /* 設定ファイルが既に存在するかの確認 */
    if (file_exists($vsfile) || file_exists($vsdir)) {
        $err_msg = sprintf($msgarr['28070'][SCREEN_MSG], $vsfile);
        $log_msg = sprintf($msgarr['28070'][LOG_MSG], $vsfile);
        return FALSE;
    }
    
    /* tmplファイルのtagの置換 */
    $conftmpl = file_get_contents($tmplfile);
    if ($conftmpl === FALSE) {
        $err_msg = sprintf($msgarr['28070'][SCREEN_MSG], $vsfile);
        $log_msg = sprintf($msgarr['28070'][LOG_MSG], $vsfile);
        return FALSE;
    }
    
    $tag["<<IP_ADDRESS>>"] = $post["ipaddress"];
    $tag["<<PORT>>"] = $post["port"];
    $tag["<<PROTOCOL_NUMBER>>"] = $post["protocol"];

    $tag["<<DELAY_LOOP>>"] = 'delay_loop '.$post["delay_loop"];
    $tag["<<LB_ALGO>>"] = 'lb_algo '.$lb_algo[$post["lb_algo"]];
    $tag["<<LB_KIND>>"] = 'lb_kind '.$lb_kind[$post["lb_kind"]];
    $tag["<<PERSISTENCE TIMEOUT>>"] = 'persistence_timeout '.$post["persistence_timeout"];
    $tag["<<PROTOCOL>>"] = 'protocol '.$protocol[$post["protocol"]];

    if ($post["virtualhost"] === "") {
        $tag["<<VIRTUALHOST>>"] = "";
    } else {
        $tag["<<VIRTUALHOST>>"] = 'virtualhost '.$post["virtualhost"];
    }

    if ($post["sorry_server_ipaddress"] == "") {
        $tag["<<SORRY_SERVER>>"] = "";
    } else {
        $tag["<<SORRY_SERVER>>"] = 'sorry_server '.$sorry;
    }

    $conftemp = change_template_tag($conftmpl, $tag);

    /* ファイル作成 */
    $tmpfname = tempnam($dirs, $vsfile);
    if ($tmpfname === FALSE) {
        $err_msg = sprintf($msgarr['28070'][SCREEN_MSG], $vsfile);
        $log_msg = sprintf($msgarr['28070'][LOG_MSG], $vsfile);
        return FALSE;
    }

    $fh = fopen($tmpfname, "w");
    if ($fh === FALSE) {
        $err_msg = sprintf($msgarr['28070'][SCREEN_MSG], $vsfile);
        $log_msg = sprintf($msgarr['28070'][LOG_MSG], $vsfile);
        return FALSE;
    }

    if (fwrite($fh, $conftemp) === FALSE) {
        $err_msg = sprintf($msgarr['28070'][SCREEN_MSG], $vsfile);
        $log_msg = sprintf($msgarr['28070'][LOG_MSG], $vsfile);
        return FALSE;
    }
    fclose($fh);

    if (rename($tmpfname, $vsfile) === FALSE) {
        $err_msg = sprintf($msgarr['28070'][SCREEN_MSG], $vsfile);
        $log_msg = sprintf($msgarr['28070'][LOG_MSG], $vsfile);
        return FALSE;
    }

    /* ディレクトリ作成 */
    if (mkdir($vsdir, 0755) === FALSE) {
        $err_msg = sprintf($msgarr['28071'][SCREEN_MSG], $vsdir);
        $log_msg = sprintf($msgarr['28071'][LOG_MSG], $vsdir);
        return FALSE;
    }

    /* real_server.conf作成 */
    if (touch($rslist) === FALSE) {
        $err_msg = sprintf($msgarr['28070'][SCREEN_MSG], $vsfile);
        $log_msg = sprintf($msgarr['28070'][LOG_MSG], $vsfile);
        return FALSE;
    }

    return TRUE;
}

/***********************************************************
 * add_vslist
 *
 *$dataの変更
 *
 * [引数]
 *         $post             入力されたデータ
 *         $file             virtual_server.confのパス
 *         $tmplfile         virtual_server.conf.tmplのパス
 *         &$data            編集するデータ   
 *
 * [返り値]
 *         TRUE              正常
 *         FALSE             異常
 **********************************************************/
function add_vslist ($post, $file, $tmplfile, &$data)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;

    $result = make_filedir($tmplfile, $post);
    if ($result === FALSE) {
        result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
        return FALSE;
    }

    $result = read_vslist($file, $data);
    if ($result === FALSE) {
        result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
        return FALSE;
    }

    /* $dataに追加 */
    $push["able"] = 'disable';
    $push["ipaddress"] = $post["ipaddress"];
    $push["port"] = $post["port"];
    $push["protocol"] = $post["protocol"];
    $data[] = $push;

    $result = write_vslist($file, $data);
    if ($result === FALSE){
        result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
        return FALSE;
    }

    return TRUE;
}

/***********************************************************
 * hold_tag
 *
 * エラー時の入力内容の保持
 *
 * [引数]
 *        $post               入力された値
 *        $algo_list          分散アルゴリズム一覧
 *        $kind_list          転送方法一覧
 *        $protocol_list      プロトコル一覧
 * [返り値]
 *        $tag                保持されたタグ
 **********************************************************/
function hold_tag ($post, $algo_list, $kind_list, $protocol_list)
{
    $tag["<<IPADDRESS>>"]              = escape_html($post["ipaddress"]);
    $tag["<<PORT>>"]                   = escape_html($post["port"]);
    $tag["<<DELAY_LOOP>>"]             = escape_html($post["delay_loop"]);
    $tag["<<PERSISTENCE_TIMEOUT>>"]    = escape_html($post["persistence_timeout"]);
    $tag["<<VIRTUALHOST>>"]            = escape_html($post["virtualhost"]);
    $tag["<<SORRY_SERVER_IPADDRESS>>"] = escape_html($post["sorry_server_ipaddress"]);
    $tag["<<SORRY_SERVER_PORT>>"]      = escape_html($post["sorry_server_port"]);

    /* selectedを付ける */
    $tag["<<LB_ALGO>>"] = "";
    foreach($algo_list as $key => $value) {
        if ($post["lb_algo"] == $key) {
            $tag["<<LB_ALGO>>"] .= "<option value=\"$key\" selected>".$value."</option>\n";
        } else {
            $tag["<<LB_ALGO>>"] .= "<option value=\"$key\">".$value."</option>\n";
        }
    }

    $tag["<<LB_KIND>>"] = "";
    foreach($kind_list as $key => $value) {
        if ($post["lb_kind"] == $key) {
            $tag["<<LB_KIND>>"] .= "<option value=\"$key\" selected>".$value."</option>\n";
        } else {
            $tag["<<LB_KIND>>"] .= "<option value=\"$key\">".$value."</option>\n";
        }
    }

    $tag["<<PROTOCOL>>"] = "";
    foreach($protocol_list as $key => $value) {
        if ($post["protocol"] == $key) {
            $tag["<<PROTOCOL>>"] .= "<option value=\"$key\" selected>".$value."</option>\n";
        } else {
            $tag["<<PROTOCOL>>"] .= "<option value=\"$key\">".$value."</option>\n";
        }
    }

    return $tag;
}

/***********************************************************
 * 初期処理
 **********************************************************/
/* 設定ファイル、タブ管理ファイル読込、セッションチェック */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit (1);
}


$filename = $web_conf["iluka"]["keepalivedbasedir"]."virtual_server.conf";
$tmplfilename = "../../../../tmpl/iluka/virtual_server.conf.tmpl";

$add_result = "";

/* タグ初期化 */
$tag["<<IPADDRESS>>"]              = "";
$tag["<<PORT>>"]                   = "";
$tag["<<PROTOCOL_NUMBER>>"]        = "";
$tag["<<PROTOCOL>>"]               = "";
$tag["<<DELAY_LOOP>>"]             = "";
$tag["<<PERSISTENCE_TIMEOUT>>"]    = "";
$tag["<<VIRTUALHOST>>"]            = "";
$tag["<<SORRY_SERVER_IPADDRESS>>"] = "";
$tag["<<SORRY_SERVER_PORT>>"]      = "";

/* 選択欄の要素 */
$algo_list = array('ラウンドロビン (rr)',
                   '重み付けラウンドロビン (wrr)',
                   '最小接続 (lc)',
                   '重み付け最小接続 (wlc)',
                   '接続元ベース最小接続 (lblc)',
                   '接続元ハッシュ (sh)',
                   '接続先ハッシュ (dh)');

$kind_list = array('ネットワークアドレス交換 (NAT)',
                   'ダイレクトルーティング (DR)',
                   'トンネリング (TUN)');

$protocol_list = array('TCP', 'UDP');

$protocol_name = array('tcp', 'udp');

/***********************************************************
 * main処理
 **********************************************************/
if (isset($_POST["update"])) {

    if (check_form($_POST)) {

        /* ipv6の整形 */
        $_POST["ipaddress"] = inet_ntop(inet_pton($_POST["ipaddress"]));

        $lock_fh = lock_file();
        if ($lock_fh === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            syserr_display();
            exit(0);
        }

        $add_result = add_vslist($_POST, $filename, $tmplfilename, $data);

        $result = unlock_file($lock_fh);
        if ($result === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            syserr_display();
            exit(0);
        }
  
        if ($add_result === TRUE) {
            $msg = "バーチャルサーバ " . $_POST["ipaddress"] . " " .
                   $_POST["port"] .  "/" . $protocol_name[$_POST["protocol"]] .
                   " の設定を追加しました。";
            dgp_location("index.php", $msg);
            exit(0);
        }

        result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
    }

    $tag = hold_tag($_POST, $algo_list, $kind_list, $protocol_list);    

/* 初期表示処理 */
} else {

    $tag["<<LB_ALGO>>"] = "";
    foreach($algo_list as $key => $value) {
        $tag["<<LB_ALGO>>"] .= "<option value=\"$key\">".$value."</option>\n";
    }
    $tag["<<LB_KIND>>"] = "";
    foreach($kind_list as $key => $value) {
        $tag["<<LB_KIND>>"] .= "<option value=\"$key\">".$value."</option>\n";
    }

    $tag["<<PROTOCOL>>"] = "";
    foreach($protocol_list as $key => $value) {
        $tag["<<PROTOCOL>>"] .= "<option value=\"$key\">".$value."</option>\n";
    }
}

/***********************************************************
 * 表示処理
 **********************************************************/

/* タグ 設定 */
set_tag_common($tag);

/* ページの出力 */
$ret = display(TMPLFILE, $tag, array(), "", "");
if ($ret === FALSE) {
    result_log($log_msg, LOG_ERR);
    syserr_display();
    exit(1);
}

?>
