<?php

namespace app\core\model;

use app\core\entity\Adm;
use app\core\entity\Department;
use app\core\entity\Gender;
use app\core\entity\Grade;
use app\core\entity\Section;
use app\core\entity\Status;
use app\core\entity\User;
use app\core\entity\WhoIam;
use PDO;
use PDOException;

class AdmModel extends Model {

    private $table = "adms";

    public function getUsers(int $status_id) : array {
        $users = array();

        $sql = "SELECT * FROM users WHERE status_id=:status_id";
        $data = $this->query($sql, [
            [":status_id", $status_id, PDO::PARAM_INT]
        ])->execute()->fetchAll();
        foreach($data as $d) {
            $user = new User(
                $d["id"], $d["first_name"],
                $d["last_name"], $d["email"], $d["phone"],
                Gender::get($d["gender_id"]),
                Department::get($d["department_id"]), 
                WhoIam::get($d["whoiam_id"]),
                Section::get($d["section_id"]),
                Grade::get($d["grade_id"]),
                $d["user_name"], $d["pwd"],
                $d["date_ins"], $d["uniqid"],
                Status::tryFrom($d["status_id"]) ?? Status::UNKNOWN,
                $d["last_activity"]
            );

            array_push($users, $user);
        }

        return $users;
    }

    public function setTable(string $table) : AdmModel {
        $this->table = $table;
        return $this;
    }

    public function getAdm(string $user_name, string $pwd) : Adm {
        $adm = new Adm;

        $sql = "SELECT *, TIMESTAMPDIFF(SECOND, last_activity, now()) as tdiff FROM " . $this->table . " WHERE (user_name=:user_name AND pwd=ENCRYPT_PASSWORD(:pwd))";
        $data = $this->query($sql, [
            [":user_name", $user_name, PDO::PARAM_STR],
            [":pwd", $pwd, PDO::PARAM_STR]
        ])->execute()->fetchAll();
        if(count($data)) {
            $adm = new Adm($data[0]["id"], $data[0]["first_name"],
                $data[0]["last_name"], $data[0]["email"], $data[0]["phone"],
                $data[0]["gender_id"], $data[0]["section_id"],
                $data[0]["user_name"], $data[0]["pwd"],
                $data[0]["date_ins"], $data[0]["uniqid"],
                Status::tryFrom($data[0]["status_id"]) ?? Status::UNKNOWN,
                $data[0]["last_activity"]
            );

            $status = self::getStatus([
                'uniqid' => $data[0]['uniqid'],
                'status_id' => $data[0]['status_id'],
                'tdiff' => $data[0]['tdiff']
            ]);
            $adm->setStatus($status);
        }

        return $adm;
    }

    static public function getStatus(array $ar) : Status {
        if(count($ar) <= 0) return Status::UNKNOWN;

        $status = Status::get($ar['status_id']);
        if($status == Status::REQUESTED) return $status;

        if($status == Status::CONNECTED) {
            // same device/browser
            if($ar['uniqid'] == $_COOKIE['adm_uniqid']) {
                if($ar['tdiff'] <= ONLINE_DURATION) {
                    return Status::ONLINE;
                }
                else {
                    if($ar['tdiff'] <= ACTIVE_DURATION) {
                        return Status::ACTIVE;
                    }
                    else {
                        return Status::INACTIVE;
                    }
                    return Status::OFFLINE;
                }
            }
            elseif($ar['tdiff'] > ACTIVE_DURATION) {
                return Status::DISCONNECTED;
            }
        }

        return $status;
    }

    public function getStatusDetails(string $user_name) : array {
        $sql = "SELECT uniqid, status_id, TIMESTAMPDIFF(SECOND, last_activity, now()) as tdiff FROM " . $this->table . " WHERE user_name=:user_name";
        $data = $this->query($sql, [
            [":user_name", $user_name, PDO::PARAM_STR]
        ])->execute()->fetchAll();
        if(count($data)) {
            return [
                'uniqid' => $data[0]['uniqid'],
                'status_id' => $data[0]['status_id'],
                'tdiff' => $data[0]['tdiff']
            ];
        }

        return array();
    }

    public function updateLastActivity(string $user_name) : bool {
        try {
            $sql = "UPDATE " . $this->table . " SET last_activity=now() WHERE user_name=:user_name";
            $c = $this->query($sql, [
                [":user_name", $user_name, PDO::PARAM_STR]
            ])->execute();
            return true;
        }
        catch(PDOException $e) {
            echo "Update last activity error message: " . $e->getMessage();
        }
        return false;
    }

    public function updateUniqId(string $user_name, string $uniqid) : bool {
        try {
            $sql = "UPDATE " . $this->table . " SET uniqid=:uniqid WHERE user_name=:user_name";
            $c = $this->query($sql, [
                [":uniqid", $uniqid, PDO::PARAM_STR],
                [":user_name", $user_name, PDO::PARAM_STR]
            ])->execute();
            return true;
        }
        catch(PDOException $e) {
            echo "Update uniqid error message: " . $e->getMessage();
        }
        return false;
    }

    public function updateStatus(string $user_name, Status $status=Status::OFFLINE) : bool {
        try {
            $sql = "UPDATE " . $this->table . " SET status_id=:status_id WHERE user_name=:user_name";
            $c = $this->query($sql, [
                [":status_id", $status->value, PDO::PARAM_STR],
                [":user_name", $user_name, PDO::PARAM_STR]
            ])->execute();
            return true;
        }
        catch(PDOException $e) {
            echo "Update status error message: " . $e->getMessage();
        }
        return false;
    }

    public function isOnline(string $user_name) : bool {
        $sql = "SELECT * FROM " . $this->table . " WHERE user_name=:user_name";
        $data = $this->query($sql, [
            [":user_name", $user_name, PDO::PARAM_STR]
        ])->execute()->fetchAll();
        if(count($data)) {
            return Status::get($data[0]["status"]) == Status::ONLINE;
        }
        return false;
    }
}