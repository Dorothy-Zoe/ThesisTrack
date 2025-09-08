<?php
class LoginService
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Login user by role
     *
     * @param string $email
     * @param string $password
     * @param string $role (advisor, student, coordinator)
     * @return array ['success' => bool, 'user' => array|null, 'message' => string|null]
     */
    public function login($email, $password, $role)
    {
        $table = '';
        $idField = '';
        switch ($role) {
            case 'advisor':
                $table = 'advisors';
                $idField = 'id';
                break;
            case 'student':
                $table = 'students';
                $idField = 'id';
                break;
            case 'coordinator':
                $table = 'coordinators';
                $idField = 'id';
                break;
            default:
                return ['success' => false, 'message' => 'Invalid role'];
        }

        try {
            $stmt = $this->pdo->prepare("SELECT * FROM $table WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'message' => 'No user found.'];
            }

            if (!password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => 'Incorrect password.'];
            }

            // Optional: update last login for advisors, students, coordinators
            $updateStmt = $this->pdo->prepare("UPDATE $table SET last_login = NOW() WHERE $idField = ?");
            $updateStmt->execute([$user[$idField]]);

            return ['success' => true, 'user' => $user];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Login failed. Please try again later.'];
        }
    }
}
