<?php

namespace local_suap;

require_once('../locallib.php');
require_once("servicelib.php");

class get_atualizacoes_counts_service extends \local_suap\service{

    function do_call() {
        global $DB, $USER;

        $USER = $DB->get_record('user', ['username' => $_GET['username']]);
        if ($USER) {
            return $this->get_atualizacoes_counts($USER->id);
        } else {
            return [
                'error' => ['message' => "Usuário '{$_GET['username']}' não existe", 'code' => 404],
                'unread_conversations_count' => 0,
                'unread_popup_notification_count' => 0,
            ];
        }
    }

    function get_atualizacoes_counts($useridto) {
        return [
            "unread_conversations_count" => \core_message_external::get_unread_conversations_count($useridto),
            "unread_popup_notification_count" => \message_popup_external::get_unread_popup_notification_count($useridto),
        ];
    }

}