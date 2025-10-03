<?php
namespace App\Middleware;

use PDO;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class TokenMiddleware
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function __invoke(Request $request, Response $response, $next)
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (!$authHeader || stripos($authHeader, 'Bearer ') !== 0) {
            return $this->unauthorized($response, 'Missing or invalid Authorization header');
        }

        $token = substr($authHeader, 7);
        if (!$token) {
            return $this->unauthorized($response, 'Token not provided');
        }

        $stmt = $this->pdo->prepare('SELECT id, first_name, last_name, email, api_token_expires_at FROM users WHERE api_token = :token LIMIT 1');
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();

        if (!$user) {
            return $this->unauthorized($response, 'Invalid token');
        }

        if (!empty($user['api_token_expires_at']) && strtotime($user['api_token_expires_at']) < time()) {
            return $this->unauthorized($response, 'Token expired');
        }

        $request = $request->withAttribute('user', [
            'id' => (int)$user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
        ]);

        $response = $next($request, $response);
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function unauthorized(Response $response, string $message)
    {
        $payload = json_encode(['error' => $message]);
        $response->getBody()->write($payload);
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }
}
