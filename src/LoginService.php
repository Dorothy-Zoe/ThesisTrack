<?php
// src/LoginService.php
require_once __DIR__ . '/../db/db.php';

class LoginService {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function loginAdvisor($email, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM advisors WHERE email = ?");
        $stmt->execute([$email]);
        $advisor = $stmt->fetch();

        if (!$advisor) return "No user found";
        if (!password_verify($password, $advisor['password'])) return "Incorrect password";

        return [
            "id" => $advisor['id'],
            "role" => "advisor",
            "name" => $advisor['first_name'] . " " . $advisor['last_name'],
            "email" => $advisor['email'],
            "requires_password_change" => $advisor['requires_password_change']
        ];
    }

    public function loginStudent($email, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM students WHERE email = ?");
        $stmt->execute([$email]);
        $student = $stmt->fetch();

        if (!$student) return "No user found";
        if (!password_verify($password, $student['password'])) return "Incorrect password";

        return [
            "id" => $student['id'],
            "role" => "student",
            "name" => $student['first_name'] . " " . $student['last_name'],
            "email" => $student['email'],
            "course" => $student['course'],
            "section" => $student['section'],
            "year_level" => $student['year_level'],
            "requires_password_change" => $student['requires_password_change']
        ];
    }

    public function loginCoordinator($email, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM coordinators WHERE email = ?");
        $stmt->execute([$email]);
        $coordinator = $stmt->fetch();

        if (!$coordinator) return "No user found";
        if (!password_verify($password, $coordinator['password'])) return "Incorrect password";

        return [
            "id" => $coordinator['id'],
            "role" => "coordinator",
            "name" => $coordinator['first_name'] . " " . $coordinator['last_name'],
            "email" => $coordinator['email']
        ];
    }
}
