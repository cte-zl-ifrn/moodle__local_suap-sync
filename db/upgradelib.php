<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin upgrade helper functions are defined here.
 *
 * @package     local_suap
 * @category    upgrade
 * @copyright   2022 Kelson Medeiros <kelsoncm@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @see         https://docs.moodle.org/dev/Data_definition_API
 * @see         https://docs.moodle.org/dev/XMLDB_creating_new_DDL_functions
 * @see         https://docs.moodle.org/dev/Upgrade_API
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/suap/locallib.php');
function suap_save_course_custom_field($categoryid, $shortname, $name, $type = 'text', $configdata = '{"required":"0","uniquevalues":"0","displaysize":50,"maxlength":250,"ispassword":"0","link":"","locked":"0","visibility":"0"}')
{
    return \local_suap\get_or_create(
        'customfield_field',
        ['shortname' => $shortname],
        ['categoryid' => $categoryid, 'name' => $name, 'type' => $type, 'configdata' => $configdata, 'timecreated' => time(), 'timemodified' => time(), 'sortorder' => \local_suap\get_last_sort_order('customfield_field')]
    );
}


function suap_save_user_custom_field($categoryid, $shortname, $name, $datatype = 'text', $visible = 1, $p1 = NULL, $p2 = NULL)
{
    return \local_suap\get_or_create(
        'user_info_field',
        ['shortname' => $shortname],
        ['categoryid' => $categoryid, 'name' => $name, 'description' => $name, 'descriptionformat' => 2, 'datatype' => $datatype, 'visible' => $visible, 'param1' => $p1, 'param2' => $p2]
    );
}


function suap_bulk_course_custom_field()
{
    global $DB;
    $cid = \local_suap\get_or_create(
        'customfield_category',
        ['name' => 'SUAP', 'component' => 'core_course', 'area' => 'course'],
        ['sortorder' => \local_suap\get_last_sort_order('customfield_category'), 'itemid' => 0, 'contextid' => 1, 'descriptionformat' => 0, 'timecreated' => time(), 'timemodified' => time()]
    )->id;
    suap_save_course_custom_field($cid, 'campus_id', 'ID do campus');
    suap_save_course_custom_field($cid, 'campus_descricao', 'Descrição do campus');
    suap_save_course_custom_field($cid, 'campus_sigla', 'Sigla do campus');

    suap_save_course_custom_field($cid, 'curso_id', 'ID do curso');
    suap_save_course_custom_field($cid, 'curso_codigo', 'Código do curso');
    suap_save_course_custom_field($cid, 'curso_descricao', 'Descrição do curso');
    suap_save_course_custom_field($cid, 'curso_nome', 'Nome do curso');
    suap_save_course_custom_field($cid, 'curso_sala_coordenacao', 'É sala de coordenação');

    suap_save_course_custom_field($cid, 'turma_id', 'ID da turma');
    suap_save_course_custom_field($cid, 'turma_codigo', 'Código da turma');

    suap_save_course_custom_field($cid, 'turma_ano_periodo', 'Ano/Semestre da turma');

    suap_save_course_custom_field($cid, 'diario_id', 'ID do diario');
    suap_save_course_custom_field($cid, 'diario_situacao', 'Situação do diario');

    suap_save_course_custom_field($cid, 'disciplina_id', 'ID da disciplina');
    suap_save_course_custom_field($cid, 'disciplina_descricao', 'Descrição da disciplina');
    suap_save_course_custom_field($cid, 'disciplina_descricao_historico', 'Descrição da disciplina que constará no histórico');
    suap_save_course_custom_field($cid, 'disciplina_sigla', 'Sigla da disciplina');
    suap_save_course_custom_field($cid, 'disciplina_periodo', 'Período da disciplina');
    suap_save_course_custom_field($cid, 'disciplina_tipo', 'Tipo da disciplina');
    suap_save_course_custom_field($cid, 'disciplina_optativo', 'Optativo da disciplina');
    suap_save_course_custom_field($cid, 'disciplina_qtd_avaliacoes', 'Quantidade de avaliações da disciplina');

    suap_save_course_custom_field($cid, 'carga_horaria', 'Carga horária', 'number');
    suap_save_course_custom_field($cid, 'tem_certificado', 'Tem certificado', 'checkbox');
}


function suap_bulk_user_custom_field()
{
    global $DB;

    $cid = \local_suap\get_or_create('user_info_category', ['name' => 'SUAP'], ['sortorder' => \local_suap\get_last_sort_order('user_info_category')])->id;

    suap_save_user_custom_field($cid, 'email_google_classroom', 'E-mail @escolar (Google Classroom');
    suap_save_user_custom_field($cid, 'email_academico', 'E-mail @academico (Microsoft)');
    suap_save_user_custom_field($cid, 'email_secundario', 'Secundário (servidores)');

    suap_save_user_custom_field($cid, 'campus_id', 'ID do campus');
    suap_save_user_custom_field($cid, 'campus_descricao', 'Descrição do campus');
    suap_save_user_custom_field($cid, 'campus_sigla', 'Sigla do campus');

    suap_save_user_custom_field($cid, 'curso_id', 'ID do curso');
    suap_save_user_custom_field($cid, 'curso_codigo', 'Código do curso');
    suap_save_user_custom_field($cid, 'curso_descricao', 'Descrição do curso');

    suap_save_user_custom_field($cid, 'turma_id', 'ID da turma');
    suap_save_user_custom_field($cid, 'turma_codigo', 'Código da turma');

    suap_save_user_custom_field($cid, 'polo_id', 'ID do pólo');
    suap_save_user_custom_field($cid, 'polo_nome', 'Nome do pólo');

    suap_save_user_custom_field($cid, 'ingresso_periodo', 'Período de ingresso');

    suap_save_user_custom_field($cid, 'nome_apresentacao', 'Nome de apresentação');
    suap_save_user_custom_field($cid, 'nome_completo', 'Nome completo');
    suap_save_user_custom_field($cid, 'nome_social', 'Nome social');
    suap_save_user_custom_field($cid, 'tipo_usuario', 'Tipo de usuário');

    suap_save_user_custom_field($cid, 'programa_nome', 'Nome do programa');

    suap_save_user_custom_field($cid, 'last_login', 'JSON do último login', 'textarea', 0);
}

function local_suap_migrate($oldversion)
{
    global $DB;

    $dbman = $DB->get_manager();

    $suap_enrolment_to_sync = new xmldb_table("suap_enrolment_to_sync");
    $suap_enrolment_to_sync->add_field("id",             XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE,  null, null, null);
    $suap_enrolment_to_sync->add_field("json",           XMLDB_TYPE_TEXT,    'medium',   XMLDB_UNSIGNED, null,          null,            null, null, null);
    $suap_enrolment_to_sync->add_field("timecreated",    XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
    $suap_enrolment_to_sync->add_field("processed",      XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);

    $suap_enrolment_to_sync->add_key("primary",      XMLDB_KEY_PRIMARY,  ["id"],         null,       null);
    if (!$dbman->table_exists($suap_enrolment_to_sync)) {
        $dbman->create_table($suap_enrolment_to_sync);
    }

    $suap_learning_path = new xmldb_table("suap_learning_path");
    $suap_learning_path->add_field("id",             XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE,  null, null, null);
    $suap_learning_path->add_field("name",           XMLDB_TYPE_CHAR,    '255',      null,           XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path->add_field("description",    XMLDB_TYPE_TEXT,    'medium',   XMLDB_UNSIGNED, null,          null,            null, null, null);
    $suap_learning_path->add_field("descriptionformat", XMLDB_TYPE_INTEGER, '2',     XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path->add_field("slug",           XMLDB_TYPE_CHAR,    '255',      null,           XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path->add_field("timecreated",    XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path->add_field("timemodified",   XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path->add_field("visible",        XMLDB_TYPE_INTEGER, '1',        XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path->add_field("sortorder",      XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);

    $suap_learning_path->add_key("primary",      XMLDB_KEY_PRIMARY,  ["id"],         null,       null);
    if (!$dbman->table_exists($suap_learning_path)) {
        $dbman->create_table($suap_learning_path);
    }

    $suap_learning_path_course = new xmldb_table("suap_learning_path_course");
    $suap_learning_path_course->add_field("id",             XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE,  null, null, null);
    $suap_learning_path_course->add_field("learningpathid", XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path_course->add_field("courseid",       XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path_course->add_field("timecreated",    XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path_course->add_field("timemodified",   XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path_course->add_field("visible",        XMLDB_TYPE_INTEGER, '1',        XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);
    $suap_learning_path_course->add_field("sortorder",      XMLDB_TYPE_INTEGER, '10',       XMLDB_UNSIGNED, XMLDB_NOTNULL, null,            null, null, null);

    $suap_learning_path_course->add_key("primary",      XMLDB_KEY_PRIMARY,  ["id"],         null,       null);
    $suap_learning_path_course->add_key("learningpathid", XMLDB_KEY_FOREIGN, ["learningpathid"], "suap_learning_path", ["id"]);
    $suap_learning_path_course->add_key("courseid",       XMLDB_KEY_FOREIGN, ["courseid"],       "course",            ["id"]);
    if (!$dbman->table_exists($suap_learning_path_course)) {
        $dbman->create_table($suap_learning_path_course);
    }

    return true;
}
