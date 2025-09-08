<?php
require_once __DIR__ . '/../db/db.php';

class LoginService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function loginAdvisor(string $email, string $password)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM advisors WHERE email = ?");
        $stmt->execute([$email]);
        $advisor = $stmt->fetch();
        if ($advisor && password_verify($password, $advisor['password'])) {
            return $advisor;
        }
        return false;
    }

    public function loginStudent(string $email, string $password)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM students WHERE email = ?");
        $stmt->execute([$email]);
        $student = $stmt->fetch();
        if ($student && password_verify($password, $student['password'])) {
            return $student;
        }
        return false;
    }

    public function loginCoordinator(string $email, string $password)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM coordinators WHERE email = ?");
        $stmt->execute([$email]);
        $coordinator = $stmt->fetch();
        if ($coordinator && password_verify($password, $coordinator['password'])) {
            return $coordinator;
        }
        return false;
    }
}
?>
