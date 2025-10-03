<?php
namespace App\Controllers;

use PDO;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class AuthController
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function register(Request $request, Response $response)
    {
        $data = $this->getRequestData($request);
        $firstName = trim($data['first_name'] ?? '');
        $middleName = trim($data['middle_name'] ?? '');
        $lastName = trim($data['last_name'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';

        if (!$firstName || !$lastName || !$email || !$password) {
            return $this->json($response, 422, ['error' => 'Missing required fields']);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json($response, 422, ['error' => 'Invalid email']);
        }

        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            return $this->json($response, 409, ['error' => 'Email already registered']);
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->pdo->prepare('INSERT INTO users (first_name, middle_name, last_name, email, password, is_verified, created_at, updated_at) VALUES (:first_name, :middle_name, :last_name, :email, :password, :is_verified, NOW(), NOW())');
        $stmt->execute([
            ':first_name' => $firstName,
            ':middle_name' => $middleName ?: null,
            ':last_name' => $lastName,
            ':email' => $email,
            ':password' => $hashed,
            ':is_verified' => 1,
        ]);

        $userId = (int)$this->pdo->lastInsertId();

        return $this->json($response, 201, [
            'message' => 'Registered successfully',
            'user_id' => $userId,
        ]);
    }

    public function login(Request $request, Response $response)
    {
        $data = $this->getRequestData($request);
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';

        if (!$email || !$password) {
            return $this->json($response, 422, ['error' => 'Email and password required']);
        }

        $stmt = $this->pdo->prepare('SELECT id, password FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return $this->json($response, 401, ['error' => 'Invalid credentials']);
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTime('+1 day'))->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare('UPDATE users SET api_token = :token, api_token_expires_at = :expires, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            ':token' => $token,
            ':expires' => $expiresAt,
            ':id' => $user['id'],
        ]);

        return $this->json($response, 200, [
            'token_type' => 'Bearer',
            'access_token' => $token,
            'expires_at' => $expiresAt,
        ]);
    }

    private function json(Response $response, int $status, array $data)
    {
        $response = $response->withStatus($status);
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function getRequestData(Request $request): array
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $rawBody = (string)$request->getBody();
            $json = json_decode($rawBody, true);
            $data = is_array($json) ? $json : [];
        }
        return $data;
    }
}
