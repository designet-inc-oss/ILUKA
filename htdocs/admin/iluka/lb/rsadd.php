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
 * リアルサーバ追加画面
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

define("OPERATION", "Add real_server");
define("TMPLFILE", "iluka_lb_rsadd.tmpl");

/***********************************************************
 * add_rslist
 *
 *$dataの変更
 *
 * [引数]
 *         $post             入力されたデータ
 * [返り値]
 *         TRUE              正常
 *         FALSE             異常
 **********************************************************/
function add_rslist ($post)
{
    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;

    $vsiport    = $post["ipaddress"]."_".$post["port"]."_".$post["protocol"];
    $rsiport    = $post["rs_ipaddress"]."_".$post["rs_port"];
    $rsconffile = $web_conf["iluka"]["keepalivedbasedir"]."virtual_server/$vsiport/real_server.conf";

    if(make_rsfile($post, $vsiport, $rsiport) === FALSE) {
        result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
        return FALSE;
    }

    if(read_rslist($rsconffile, $data) === FALSE) {
        result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
        return FALSE;
    }

    /* 既に追加したいIP_PORTが存在する場合 */
    $flag = 0;
    if (count($data) > 0) {
        foreach ($data as $key => $value) {
            if ($post["rs_ipaddress"] == $value["rs_ipaddress"] && $post["rs_port"] == $value["rs_port"]) {
                if ($value["rsable"] == 'enable') {
                    $data[$key]["rsable"] = 'disable';
                    $flag = 1;
                    break;
                } else {
                    return TRUE;
                }
            }
        }
    }

    /* 正常に追加する処理 */
    if ($flag === 0) {
        $push["rsable"] = 'disable';
        $push["rs_ipaddress"] = $post["rs_ipaddress"];
        $push["rs_port"] = $post["rs_port"];
        $data[] = $push;
    }


    if (write_rslist($rsconffile, $data) === FALSE ) {
        result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
        return FALSE;
    }
    return TRUE;
}

/***********************************************************
 * make_rsfile
 *
 * リアルサーバ設定ファイル作成
 *
 * [引数]
 *       $post                入力された値
 *       $vsiport             バーチャルサーバのIP_PORT 
 *       $rsiport             リアルサーバのIP_PORT
 * [返り値]
 *       TRUE                 正常
 *       FALSE                異常
 **********************************************************/
function make_rsfile ($post, $vsiport, $rsiport)
{

    global $msgarr;
    global $err_msg;
    global $log_msg;
    global $web_conf;

    $rsdir      = $web_conf["iluka"]["keepalivedbasedir"]."virtual_server/$vsiport/";
    $rsfile     = $rsdir.$rsiport.".conf";


    /* 設定ファイルが既に存在するかの確認 */
    if (file_exists($rsfile)) {
        $err_msg = sprintf($msgarr['28083'][SCREEN_MSG], $rsfile);
        $log_msg = sprintf($msgarr['28083'][LOG_MSG], $rsfile);
        return FALSE;
    }

    if (replace_tmpls($post, $conftmpl) === FALSE) {
        return FALSE;
    }    

    if (make_rsconf($rsdir, $rsfile, $conftmpl) === FALSE) {
        return FALSE;
    }

    return TRUE;
}

/***********************************************************
 * rs_hold_tag
 *
 * エラー時の入力内容の保持
 *
 * [引数]
 *        $post               入力された値
 * [返り値]
 *        $tag                保持されたタグ
 **********************************************************/
function rs_hold_tag ($post)
{
    global $health_check;

    $tag["<<IPADDRESS>>"]               = escape_html($post["ipaddress"]);
    $tag["<<PORT>>"]                    = escape_html($post["port"]);
    $tag["<<PROTOCOL>>"]                = escape_html($post["protocol"]);
    $tag["<<RS_IPADDRESS>>"]            = escape_html($post["rs_ipaddress"]);
    $tag["<<RS_PORT>>"]                 = escape_html($post["rs_port"]);
    $tag["<<WEIGHTING>>"]               = escape_html($post["weighting"]);
    $tag["<<HELO_NAME>>"]               = escape_html($post["helo_name"]);
    $tag["<<MISC_PATH>>"]               = escape_html($post["misc_path"]);
    $tag["<<MISC_TIMEOUT>>"]            = escape_html($post["misc_timeout"]);
    $tag["<<HTTP_PATH>>"]               = escape_html($post["http_path"]);
    $tag["<<HTTP_DIGEST>>"]             = escape_html($post["http_digest"]);
    $tag["<<HTTP_STATUS_CODE>>"]        = escape_html($post["http_status_code"]);
    $tag["<<NB_GET_RETRY>>"]            = escape_html($post["nb_get_retry"]);
    $tag["<<DELAY_RETRY>>"]             = escape_html($post["http_delay_retry"]);
    $tag["<<SMTP_RETRY>>"]              = escape_html($post["smtp_retry"]);
    $tag["<<SMTP_DELAY_RETRY>>"]        = escape_html($post["smtp_delay_retry"]);
    $tag["<<DNS_NAME>>"]                = escape_html($post["dns_name"]);

    if (isset($post["udp_connect_require_reply"]) && $post["udp_connect_require_reply"] == 1) {
        $tag["<<UDP_REQUIRE_REPLY>>"]  = "<option value=\"0\">チェックしない</option>"
                                       . "<option value=\"1\" selected>チェックする</option>";
    } else {
        $tag["<<UDP_REQUIRE_REPLY>>"]  = "<option value=\"0\" selected>チェックしない</option>"
                                       . "<option value=\"1\">チェックする</option>";
    }

    $dns_type_list = array("SOA", "A", "NS", "CNAME", "MX", "TXT", "AAAA");
    $tag["<<DNS_TYPE>>"]  = "";
    foreach ($dns_type_list as $value) {
        if ($post["dns_type"] === $value) {
            $tag["<<DNS_TYPE>>"]  .= "<option value=\"" . $value . "\" selected>" . $value . "</option>";
	} else {
            $tag["<<DNS_TYPE>>"]  .= "<option value=\"" . $value . "\">" . $value . "</option>";
	}
    }

    if ($post["h_check"] == 0 || $post["h_check"] == 1 || $post["h_check"] == 4) {
        $connect_ipaddress        = $post["http_connect_ipaddress"];
        $connect_port             = $post["http_connect_port"];
        $connect_source_ipaddress = $post["http_connect_source_ipaddress"];
        $connect_source_port      = $post["http_connect_source_port"];
        $connect_timeout          = $post["http_connect_timeout"];
    } elseif ($post["h_check"] == 2) {
        $connect_ipaddress        = $post["tcp_connect_ipaddress"];
        $connect_port             = $post["tcp_connect_port"];
        $connect_source_ipaddress = $post["tcp_connect_source_ipaddress"];
        $connect_source_port      = $post["tcp_connect_source_port"];
        $connect_timeout          = $post["tcp_connect_timeout"];
    } elseif ($post["h_check"] == 3) {
        $connect_ipaddress        = $post["smtp_connect_ipaddress"];
        $connect_port             = $post["smtp_connect_port"];
        $connect_source_ipaddress = $post["smtp_connect_source_ipaddress"];
        $connect_source_port      = $post["smtp_connect_source_port"];
        $connect_timeout          = $post["smtp_connect_timeout"];
    } elseif ($post["h_check"] == 5) {
        $connect_ipaddress        = $post["udp_connect_ipaddress"];
        $connect_port             = $post["udp_connect_port"];
        $connect_source_ipaddress = $post["udp_connect_source_ipaddress"];
        $connect_source_port      = $post["udp_connect_source_port"];
        $connect_timeout          = $post["udp_connect_timeout"];
    } elseif ($post["h_check"] == 6) {
        $connect_ipaddress        = $post["dns_connect_ipaddress"];
        $connect_port             = $post["dns_connect_port"];
        $connect_source_ipaddress = $post["dns_connect_source_ipaddress"];
        $connect_source_port      = $post["dns_connect_source_port"];
        $connect_timeout          = $post["dns_connect_timeout"];
    }

    $tag["<<CONNECT_IPADDRESS>>"]       = escape_html($connect_ipaddress);
    $tag["<<CONNECT_PORT>>"]            = escape_html($connect_port);
    $tag["<<CONNECT_SOURCE_IPADDRESS>>"] = escape_html($connect_source_ipaddress);
    $tag["<<CONNECT_SOURCE_PORT>>"]      = escape_html($connect_source_port);
    $tag["<<CONNECT_TIMEOUT>>"]         = escape_html($connect_timeout);

    /* selectedを付ける */
    $tag["<<HEALTH_CHECK>>"] = "";
    foreach($health_check as $key => $value) {
        if ($post["h_check"] == $key) {
            $tag["<<HEALTH_CHECK>>"] .= "<option value=\"$key\" selected>".$value."</option>\n";
        } else {
            $tag["<<HEALTH_CHECK>>"] .= "<option value=\"$key\">".$value."</option>\n";
        }
    }

    return $tag;
}

/***********************************************************
 * 初期処理
 **********************************************************/
$protocol_name = array("tcp", "udp");

/* 設定ファイル、タブ管理ファイル読込、セッションチェック */
$ret = init();
if ($ret === FALSE) {
    syserr_display();
    exit (1);
}

/* バーチャルサーバが存在するかの確認 */
$result = check_vs_exists($_POST);
if ($result === 1) {
    err_location("rsadd.php?e=1");
    exit(1);
} elseif ($result === 2) {
    result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
    dgp_location("index.php", $err_msg);
    exit(0);
}

/* tmpl一覧 */
$tmpl_list = array("../../../../tmpl/iluka/http_get.tmpl",
                   "../../../../tmpl/iluka/ssl_get.tmpl",
                   "../../../../tmpl/iluka/tcp_check.tmpl",
                   "../../../../tmpl/iluka/smtp_check.tmpl",
                   "../../../../tmpl/iluka/misc_check.tmpl",
                   "../../../../tmpl/iluka/udp_check.tmpl",
                   "../../../../tmpl/iluka/dns_check.tmpl");

/* ヘルスチェック */
$health_check = array('HTTP_GET', 'SSL_GET', 'TCP_CHECK', 'SMTP_CHECK', 'MISC_CHECK', 'UDP_CHECK', 'DNS_CHECK');

$add_result = "";
/***********************************************************
 * main処理
 **********************************************************/
/* 登録ボタンが押された場合 */
if (isset($_POST["update"])) {
    if(rs_check_form($_POST)) {

        /* ipv6の整形 */
        $_POST["rs_ipaddress"] = inet_ntop(inet_pton($_POST["rs_ipaddress"]));

        $lock_fh = lock_file();
        if ($lock_fh === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            syserr_display();
            exit(0);
        }

        $add_result = add_rslist($_POST);

        $result = unlock_file($lock_fh);
        if ($result === FALSE) {
            result_log(OPERATION . ":NG:" . $log_msg, LOG_ERR);
            syserr_display();
            exit(0);
        }

        if ($add_result === TRUE) {
            $sesskey = $_POST["sk"];
            $msg = "リアルサーバ ".$_POST["rs_ipaddress"]." ".$_POST["rs_port"]." の設定を追加しました。";
            $post = array("ipaddress" => $_POST["ipaddress"],
                          "port"      => $_POST["port"],
                          "protocol"  => $_POST["protocol"]);
            iluka_location("rslist.php", $msg, $post);
            exit(0);
        }
    }

    $tag = rs_hold_tag($_POST);

/* キャンセルボタンが押された場合 */
} elseif (isset($_POST["cancel"])) {

    $sesskey = $_POST["sk"];
    $msg = "";
    $post = array("ipaddress" => $_POST["ipaddress"],
                  "port"      => $_POST["port"],
                  "protocol"  => $_POST["protocol"]);
    iluka_location("rslist.php", $msg, $post);
    exit(0);

/* 初期画面表示 */
} else {

    $tag["<<IPADDRESS>>"] = $_POST["ipaddress"];
    $tag["<<PORT>>"]      = $_POST["port"];
    $tag["<<PROTOCOL>>"]  = $_POST["protocol"];
    $tag["<<PROTOCOL_NAME>>"] = $protocol_name[$_POST["protocol"]];

    $tag["<<RS_IPADDRESS>>"]            = "";
    $tag["<<RS_PORT>>"]                 = "";
    $tag["<<HEALTH_CHECK>>"]            = "";
    $tag["<<WEIGHTING>>"]               = "";
    $tag["<<HTTP_PATH>>"]               = "";
    $tag["<<HTTP_DIGEST>>"]             = "";
    $tag["<<HTTP_STATUS_CODE>>"]        = "";
    $tag["<<NB_GET_RETRY>>"]            = "";
    $tag["<<DELAY_RETRY>>"]             = "";
    $tag["<<SMTP_RETRY>>"]              = "";
    $tag["<<SMTP_DELAY_RETRY>>"]        = "";
    $tag["<<CONNECT_IPADDRESS>>"]       = "";
    $tag["<<CONNECT_PORT>>"]            = "";
    $tag["<<CONNECT_SOURCE_IPADDRESS>>"] = "";
    $tag["<<CONNECT_SOURCE_PORT>>"]      = "";
    $tag["<<CONNECT_TIMEOUT>>"]         = "";
    $tag["<<HELO_NAME>>"]               = "";
    $tag["<<MISC_PATH>>"]               = "";
    $tag["<<MISC_TIMEOUT>>"]            = "";
    $tag["<<DNS_NAME>>"]                = "";

    foreach($health_check as $key => $value) {
        $tag["<<HEALTH_CHECK>>"] .= "<option value=\"$key\">".$value."</option>\n";
    }

     $tag["<<UDP_REQUIRE_REPLY>>"]  = "<option value=\"0\">チェックしない</option>"
                                     . "<option value=\"1\">チェックする</option>";

     $tag["<<DNS_TYPE>>"]  = "<option value=\"SOA\">SOA</option>"
                           . "<option value=\"A\">A</option>"
                           . "<option value=\"NS\">NS</option>"
                           . "<option value=\"CNAME\">CNAME</option>"
                           . "<option value=\"MX\">MX</option>"
                           . "<option value=\"TXT\">TXT</option>"
                           . "<option value=\"AAAA\">AAAA</option>";
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
