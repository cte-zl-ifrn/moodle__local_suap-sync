<?php
namespace local_suap;

require_once(\dirname(\dirname(\dirname(__DIR__))) . '/config.php');

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->dirroot . '/enrol/externallib.php');
require_once($CFG->dirroot . '/local/suap/locallib.php');
require_once($CFG->dirroot . '/local/suap/classes/Jsv4/Validator.php');
require_once($CFG->dirroot . '/local/suap/api/servicelib.php');


class sync_up_enrolments_service extends service {

    private $json;
    private $suapIssuer;
    private $diarioCategory;
    private $campusCategory;
    private $cursoCategory;
    private $semestreCategory;
    private $turmaCategory;
    private $context;
    private $course;
    private $diarioIsNew = false;
    private $diario;
    private $coordenacao;
    private $isRoom;
    private $aluno_enrol;
    private $professor_enrol;
    private $tutor_enrol;
    private $docente_enrol;
    private $studentAuth;
    private $teacherAuth;
    private $assistantAuth;
    private $default_user_preferences;

    
    function do_call() {
        $jsonstring = file_get_contents('php://input');
        $result = $this->process($jsonstring, false);
        $this->insertSyncDB($jsonstring);
        return $result;
    }

    function insertSyncDB($jsonstring){
        global $DB;
        
        $DB->insert_record("suap_enrolment_to_sync",
        (object)[
            'json'=>$jsonstring,
            'timecreated'=>time(),
            'processed'=>0
        ]
        );
    }

    function process($jsonstring, $addMembers) {
        global $CFG;
        $prefix = "{$CFG->wwwroot}/course/view.php";

        $this->validate_json($jsonstring);
        $this->sync_oauth_issuer();
        $this->sync_auths();
        $this->sync_users();
        $this->sync_categories();

        $this->isRoom = false;
        $this->sync_course($this->turmaCategory->id);
        $this->diario = $this->course;
        $this->sync_enrols();
        $this->sync_docentes_enrol();
        $this->sync_discentes_enrol();
        if ($addMembers) {
            $this->sync_groups();
        }
        $this->sync_cohorts(); // só existe em diário

        $this->isRoom = true;
        $this->sync_course($this->cursoCategory->id);
        $this->coordenacao = $this->course;
        $this->sync_enrols();
        $this->sync_docentes_enrol();
        $this->sync_discentes_enrol();
        if ($addMembers) {
            $this->sync_groups();
        }

        return ["url" => "$prefix?id={$this->diario->id}", "url_sala_coordenacao" => "$prefix?id={$this->coordenacao->id}"];
    }


    function validate_json($jsonstring) {
        $this->json = json_decode($jsonstring);

        if (!$this->json) {
            throw new \Exception("Erro ao validar o JSON, favor corrigir.");
        }

        $schema = json_decode(file_get_contents("../schemas/sync_up_enrolments.schema.json"));
        $validation = \Jsv4\Validator::validate($this->json, $schema);
        if (!\Jsv4\Validator::isValid($this->json, $schema)) {
            $errors = "";

            foreach ($validation->errors as $error) {
                $errors .= "{$error->message}";
            }
            throw new \Exception("Erro ao validar o JSON, favor corrigir." . $errors);
        }
    }

    function sync_oauth_issuer() {
        $this->suapIssuer = create_or_update(
            'oauth2_issuer', 
            [
                'name'=>'suap'
            ],
            [
                'image'=>'https://ead.ifrn.edu.br/portal/wp-content/uploads/2020/08/SUAP.png', 
                'loginscopes'=>'identificacao email',
                'loginscopesoffline'=>'identificacao email documentos_pessoais',
                'baseurl'=>'',
                'loginparams'=>'',
                'loginparamsoffline'=>'',
                'alloweddomains'=>'',
                'enabled'=>1,
                'showonloginpage'=>1,
                'basicauth'=>0,
                'sortorder'=>0,
                'timecreated'=>time(),
                'timemodified'=>time(),
                'usermodified'=>2
            ],
            [
                'requireconfirmation'=>0
            ],
            [
                'clientid'=>'changeme',
                'clientsecret'=>'changeme'
            ]
        );
    }


    function sync_auths(){
        global $DB;

        $this->studentAuth = config('default_student_auth');
        $this->teacherAuth = config('default_teacher_auth');
        $this->assistantAuth = config('default_assistant_auth');
        $this->default_user_preferences = config('default_user_preferences');
    }


    function sync_users() {
        global $CFG, $DB;
        
        $professores = isset($this->json->professores) ? $this->json->professores : [];
        $alunos = isset($this->json->alunos) ? $this->json->alunos : [];

        foreach (array_merge($professores, $alunos) as $usuario) {
            $usuario->isProfessor = isset($usuario->login);
            $usuario->isAluno = isset($usuario->matricula);
            $this->sync_user($usuario);
        }
    }


    function sync_user($usuario){
        global $DB;

        $username = $usuario->isAluno ? $usuario->matricula : $usuario->login;       
        $email = !empty($usuario->email) ? $usuario->email : $usuario->email_secundario;
        $status = strtolower($usuario->isAluno ? $usuario->situacao : $usuario->status);
        $suspended = $status == 'ativo' ? 0 : 1;

        $nome_parts = explode(' ', $usuario->nome);
        $firstname = $nome_parts[0];
        $lastname = implode(' ', array_slice($nome_parts, 1));
        
        if ($usuario->isAluno) {
            $auth = $this->studentAuth;
        } else {
            $auth = $usuario->tipo == 'Principal' ? $this->teacherAuth : $this->assistantAuth;
        }
        
        $insert_only = ['username'=>$username, 'password'=>'!aA1' . uniqid(), 'timezone'=>'99', 'confirmed'=>1, 'mnethostid'=>1];
        $insert_or_update = ['firstname'=>$firstname, 'lastname'=>$lastname, 'auth'=>$auth, 'email'=> $email, 'suspended' => $suspended];

        $usuario->user = $DB->get_record("user", ["username" => $username]);
        if ($usuario->user) {
            \user_update_user(array_merge(['id'=>$usuario->user->id], $insert_or_update));
        } else {
            \user_create_user(array_merge($insert_or_update, $insert_only));
            $usuario->user = $DB->get_record("user", ["username" => $username]);
            foreach (preg_split('/\r\n|\r|\n/', $this->default_user_preferences) as $preference) {
                $parts = explode("=", $preference);
                \set_user_preference($parts[0], $parts[1], $usuario->user);
            }
            
            get_or_create(
                'auth_oauth2_linked_login',
                ['userid'=>$usuario->user->id, 'issuerid'=>$this->suapIssuer->id, 'username'=>$username],
                ['email'=> $email, 'timecreated'=>time(), 'usermodified'=>0, 'confirmtoken'=>'', 'confirmtokenexpires'=>0, 'timemodified'=>time()],
            );
        }

        if ($usuario->isAluno) {
            $custom_fields = [
                'programa_nome' => isset($usuario->programa) ? $usuario->programa : "Institucional",
                'curso_descricao' => $this->json->curso->nome,
                'curso_codigo' => $this->json->curso->codigo
            ];
            if (property_exists($usuario, 'polo')) {
                $custom_fields['polo_id'] = property_exists($usuario->polo, 'id') ? $usuario->polo->id : null;
                $custom_fields['polo_nome'] = property_exists($usuario->polo, 'descricao') ? $usuario->polo->descricao : null;
            }
            \profile_save_custom_fields($usuario->user->id, $custom_fields);
        }
    }

    
    function sync_categories() {
        $this->diarioCategory = $this->sync_category(
            config('top_category_idnumber') ?: 'diarios',
            config('top_category_name') ?: 'Diários',
            config('top_category_parent') ?: 0
        );

        $this->campusCategory = $this->sync_category(
            $this->json->campus->sigla,
            $this->json->campus->descricao,
            $this->diarioCategory->id
        );

        $this->cursoCategory = $this->sync_category(
            $this->json->curso->codigo,
            $this->json->curso->nome,
            $this->campusCategory->id
        );

        $ano_periodo = substr($this->json->turma->codigo, 0, 4) . "." . substr($this->json->turma->codigo, 4, 1);
        $this->semestreCategory = $this->sync_category(
            "{$this->json->curso->codigo}.{$ano_periodo}",
            $ano_periodo,
            $this->cursoCategory->id
        );

        $this->turmaCategory = $this->sync_category(
            $this->json->turma->codigo,
            $this->json->turma->codigo,
            $this->semestreCategory->id
        );
    }


    function sync_category($idnumber, $name, $parent){
        global $DB;
    
        $category = $DB->get_record('course_categories', ['idnumber'=>$idnumber]);
        if (empty($category)) {
            $category = \core_course_category::create(['name'=>$name, 'idnumber'=>$idnumber, 'parent'=>$parent]);
        }   

        return $category;
    }


    function sync_course($categoryid){
        global $DB;
        
        $course_code = $this->isRoom ? "{$this->json->campus->sigla}.{$this->json->curso->codigo}" : "{$this->json->turma->codigo}.{$this->json->componente->sigla}";
        $course_code_long = $this->isRoom ? $course_code : "{$course_code}#{$this->json->diario->id}";
        $this->course = $DB->get_record('course', ['idnumber'=>$course_code_long]) ?: $DB->get_record('course', ['idnumber'=>$course_code]);
        if (!$this->course) {
            $this->course = $DB->get_record('course', ['shortname'=>$course_code_long]) ?: $DB->get_record('course', ['shortname'=>$course_code]);
        }

        if (!$this->course) {
            $data = [
                    "category"=>$categoryid,
                    "shortname"=>$course_code_long,
                    "fullname"=> $this->isRoom ? "Sala de coordenação do curso {$this->json->curso->nome}" : $this->json->componente->descricao,
                    "idnumber"=>$course_code_long,
                    "visible"=>0,
                    "enablecompletion"=>1,
                    // "startdate"=>time(),
                    "showreports"=>1,
                    "completionnotify"=>1,

                    "customfield_campus_id"=> $this->json->campus->id,
                    "customfield_campus_descricao"=> $this->json->campus->descricao,
                    "customfield_campus_sigla"=> $this->json->campus->sigla,

                    "customfield_curso_id"=> $this->json->curso->id,
                    "customfield_curso_codigo"=> $this->json->curso->codigo,
                    "customfield_curso_descricao"=> $this->json->curso->descricao,
                    "customfield_curso_nome"=> $this->json->curso->nome,
            ];

            if ($this->isRoom) {
                $data = array_merge(
                    $data,
                    [
                        "customfield_curso_sala_coordenacao"=> 'Sim',
                    ]
                );
            } else {
                $data = array_merge(
                    $data,
                    [
                        "customfield_curso_sala_coordenacao"=> 'Não',
        
                        "customfield_turma_id"=> $this->json->turma->id,
                        "customfield_turma_codigo"=> $this->json->turma->codigo,
        
                        "customfield_turma_ano_periodo"=> substr($this->json->turma->codigo, 0, 4) . "." . substr($this->json->turma->codigo, 4, 1),
        
                        "customfield_diario_id"=> $this->json->diario->id,
                        "customfield_diario_situacao"=> $this->json->diario->situacao,
        
                        "customfield_disciplina_id"=> $this->json->componente->id,
                        "customfield_disciplina_sigla"=> $this->json->componente->sigla,
                        "customfield_disciplina_descricao"=> $this->json->componente->descricao,
                        "customfield_disciplina_descricao_historico"=> $this->json->componente->descricao_historico,
                        // "customfield_disciplina_periodo"=> $this->json->componente->periodo,
                        "customfield_disciplina_tipo"=> $this->json->componente->tipo,
                        "customfield_disciplina_optativo"=> $this->json->componente->optativo,
                        "customfield_disciplina_qtd_avaliacoes"=> $this->json->componente->qtd_avaliacoes,
                    ]
                );
            }
            $this->course = create_course((object)$data);
            $this->diarioIsNew = true;
        } elseif (!$this->isRoom) {
            $this->course->idnumber = $course_code_long;
            $this->course->shortname = $course_code_long;
            update_course($this->course);
        }

        $this->context = \context_course::instance($this->course->id);
    }

    
    function sync_enrols() {
        $this->professor_enrol = $this->get_enrolment_config('teacher');
        $this->tutor_enrol = $this->get_enrolment_config('assistant');
        $this->docente_enrol = $this->get_enrolment_config('instructor');
        $this->aluno_enrol = $this->get_enrolment_config('student');
    }


    function get_enrolment_config($type)  {       
        $roleid = config("default_{$type}_role_id");
        $enrol_type = config("default_{$type}_enrol_type");
        $enrol = enrol_get_plugin($enrol_type);
        $instance = $this->get_instance($enrol_type);
        if ($instance == null) {
            $enrol->add_instance($this->course);
            $instance = $this->get_instance($enrol_type);
        }
        return (object)['roleid'=>$roleid, 'enrol_type'=>$enrol_type, 'enrol'=>$enrol, 'instance'=>$instance];
    }


    function get_instance($enrol_type) {
        foreach (\enrol_get_instances($this->course->id, FALSE) as $i) {
            if ($i->enrol == $enrol_type) {
                return $i;
            }
        }
        return null;
    }


    function sync_docentes_enrol() {
        global $CFG, $DB;

        if (isset($this->json->professores)) {
            foreach ($this->json->professores as $usuario) {
                if ($this->isRoom) {
                    $enrol = $this->docente_enrol;
                } elseif (in_array(strtolower($usuario->tipo), ['principal', 'formador']))  {
                    $enrol = $this->professor_enrol;
                } else {
                    $enrol = $this->tutor_enrol;
                }
                
                $this->sync_enrol($enrol, $usuario, \ENROL_USER_ACTIVE);
            }
        }
    }


    function sync_discentes_enrol() {
        global $CFG, $DB;
        $alunos_suspensos = [];
        $alunos_sincronizados = [];
        if (isset($this->json->alunos)) {
            foreach ($this->json->alunos as $usuario) {
                $status = isset($usuario->situacao_diario) && strtolower($usuario->situacao_diario) != 'ativo' ? \ENROL_USER_SUSPENDED : \ENROL_USER_ACTIVE;
                $this->sync_enrol($this->aluno_enrol, $usuario, $status);
                array_push($alunos_sincronizados, $usuario->user->id);
            }

            // Inativa no diário os ALUNOS que não vieram na sicronização
            if (!$this->isRoom) {
                foreach ($DB->get_records_sql("SELECT ra.userid FROM {role_assignments} ra WHERE ra.roleid = {$this->aluno_enrol->roleid} AND ra.contextid={$this->context->id}") as $userid => $ra) {
                    if (!in_array($userid, $alunos_sincronizados)) {
                        $this->aluno_enrol->enrol->update_user_enrol($this->aluno_enrol->instance, $userid, \ENROL_USER_SUSPENDED);
                    }
                }
            }
        }
    }


    function sync_enrol($enrol, $usuario, $status) {
        if (is_enrolled($this->context, $usuario->user)) {
            $enrol->enrol->update_user_enrol($enrol->instance, $usuario->user->id, $status);
        } else {
            $enrol->enrol->enrol_user($enrol->instance, $usuario->user->id, $enrol->roleid, time(), 0, $status);
        }
    }


    function sync_groups() {
        global $CFG, $DB;
        if (isset($this->json->alunos)) {
            $grupos = [];
            foreach ($this->json->alunos as $usuario) {
                $entrada = substr($usuario->user->username, 0, 5);
                $turma = $this->json->turma->codigo;
                $polo = isset($usuario->polo) && isset($usuario->polo->descricao) ? $usuario->polo->descricao : '--Sem pólo--';
                $programa = isset($usuario->programa) && $usuario->programa != null ? $usuario->programa : "Institucional";

                if (!isset($grupos[$entrada])) {$grupos[$entrada] = [];}
                if (!isset($grupos[$turma])) {$grupos[$turma] = [];}
                if (!isset($grupos[$polo])) {$grupos[$polo] = [];}
                if (!isset($grupos[$programa])) {$grupos[$programa] = [];}

                $grupos[$entrada][] = $usuario;
                $grupos[$turma][] = $usuario;
                $grupos[$polo][] = $usuario;
                $grupos[$programa][] = $usuario;
            }

            foreach ($grupos as $group_name => $alunos) {
                $group = $this->sync_group($group_name);
                $idDosAlunosFaltandoAgrupar = $this->getIdDosAlunosFaltandoAgrupar($group, $alunos);
                // $new_group_members = [];
                foreach ($alunos as $group_name => $usuario) {
                    if (!in_array($usuario->user->id, $idDosAlunosFaltandoAgrupar)) {
                        \groups_add_member($group->id, $usuario->user->id);
                        // array_push($new_group_members, (object)['groupid' => $group->id, 'userid' => $usuario->user->id, "timeadded"=>time()]);
                    }
                }
                // $DB->insert_records("groups_members", $new_group_members);
            }
            //
        }
    }

    function sync_group($group_name) {
        global $DB;
        $data = ['courseid' => $this->course->id, 'name' => $group_name];
        $group = $DB->get_record('groups', $data);
        if (!$group && $this->diarioIsNew) {
            \groups_create_group((object)$data);
            $group = $DB->get_record('groups', $data);
        }
        return $group;
    }

    function getIdDosAlunosFaltandoAgrupar($group, $alunos) {
        global $DB;
        $alunoIds = array_map(function($x) { return $x->user->id; }, $alunos);
        list($insql, $inparams) = $DB->get_in_or_equal($alunoIds);
        $sql = "SELECT userid FROM {groups_members} WHERE groupid = ? and userid $insql";
        $ja_existem = $DB->get_records_sql($sql, array_merge([$group->id], $inparams));
        return array_map(function($x) { return $x->userid; }, $ja_existem);
    }

    function sync_cohorts(){
        global $DB;
        
        $roles = [];
        $instances = [];
        $coortesid = [];
        $enrol = enrol_get_plugin("cohort");
        if (isset(($this->json->coortes))) {
            foreach ($this->json->coortes as $coorte) {
                if (!isset($instances[$coorte->role])) {
                    $instance = $DB->get_record('cohort', ['idnumber'=>$coorte->idnumber]);
                    if (!$instance) {
                        $coortesid[$coorte->role] = \cohort_add_cohort(
                            (object)[
                                "name"=>$coorte->nome, 
                                "idnumber"=>$coorte->idnumber,
                                "description"=>$coorte->descricao,
                                "visible"=>$coorte->ativo,
                                "contextid"=>1
                            ]
                        );
                    } else {
                        $instance->name = $coorte->nome;
                        $instance->idnumber = $coorte->idnumber;
                        $instance->description = $coorte->descricao;
                        $instance->visible = $coorte->ativo;
                        \cohort_update_cohort($instance);
                        $coortesid[$coorte->role] = $instance->id;
                    }
                }
                $cohortid = $coortesid[$coorte->role];

                foreach ($coorte->colaboradores as $usuario) {
                    $usuario->isAluno = False;
                    $usuario->isProfessor = False;
                    $usuario->isColaborador = True;
                    $usuario->tipo = "Staff";
                    $this->sync_user($usuario);
                    \cohort_add_member($cohortid, $usuario->user->id);
                }

                if (!isset($roles[$coorte->role])) {
                    $roles[$coorte->role] = $DB->get_record('role', ['shortname'=>$coorte->role]);
                }
                $role = $roles[$coorte->role];

                if (!isset($instances[$cohortid])) {
                    $instances[$cohortid] = $DB->get_record('enrol', ["enrol"=>"cohort", "customint1"=> $cohortid, "courseid"=>$this->course->id]);
                    if (!$instance) {
                        $enrol->add_instance($this->course, ["customint1"=>$cohortid, "roleid"=>$role->id, "customint2"=>0]);
                    }
                }
                $instance = $instances[$cohortid];
            }
        }
    }
}