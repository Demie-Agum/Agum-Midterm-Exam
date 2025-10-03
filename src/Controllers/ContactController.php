<?php
namespace App\Controllers;

use PDO;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class ContactController
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index(Request $request, Response $response)
    {
        $user = $request->getAttribute('user');
        $stmt = $this->pdo->prepare('SELECT id, contact_name, contact_number, contact_photo, created_at, updated_at FROM contacts WHERE user_id = :uid ORDER BY id DESC');
        $stmt->execute([':uid' => $user['id']]);
        $contacts = $stmt->fetchAll();
        return $this->json($response, 200, ['data' => $contacts]);
    }

    public function create(Request $request, Response $response)
    {
        $user = $request->getAttribute('user');
        $data = $this->getRequestData($request);
        $name = trim($data['contact_name'] ?? '');
        $number = trim($data['contact_number'] ?? '');
        $photo = trim($data['contact_photo'] ?? '');

        if (!$name || !$number) {
            return $this->json($response, 422, ['error' => 'contact_name and contact_number are required']);
        }

        $stmt = $this->pdo->prepare('INSERT INTO contacts (user_id, contact_name, contact_number, contact_photo, created_at, updated_at) VALUES (:uid, :name, :number, :photo, NOW(), NOW())');
        $stmt->execute([
            ':uid' => $user['id'],
            ':name' => $name,
            ':number' => $number,
            ':photo' => $photo ?: null,
        ]);

        return $this->json($response, 201, [
            'message' => 'Contact created',
            'id' => (int)$this->pdo->lastInsertId(),
        ]);
    }

    public function update(Request $request, Response $response, array $args)
    {
        $user = $request->getAttribute('user');
        $id = (int)($args['id'] ?? 0);
        if ($id <= 0) {
            return $this->json($response, 400, ['error' => 'Invalid contact id']);
        }

        $data = $this->getRequestData($request);
        $fields = [];
        $params = [':id' => $id, ':uid' => $user['id']];

        if (isset($data['contact_name'])) { $fields[] = 'contact_name = :name'; $params[':name'] = trim($data['contact_name']); }
        if (isset($data['contact_number'])) { $fields[] = 'contact_number = :number'; $params[':number'] = trim($data['contact_number']); }
        if (array_key_exists('contact_photo', $data)) { $fields[] = 'contact_photo = :photo'; $params[':photo'] = $data['contact_photo'] !== '' ? trim($data['contact_photo']) : null; }

        if (empty($fields)) {
            return $this->json($response, 422, ['error' => 'No fields to update']);
        }

        $sql = 'UPDATE contacts SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id AND user_id = :uid';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            return $this->json($response, 404, ['error' => 'Contact not found']);
        }

        return $this->json($response, 200, ['message' => 'Contact updated']);
    }

    public function delete(Request $request, Response $response, array $args)
    {
        $user = $request->getAttribute('user');
        $id = (int)($args['id'] ?? 0);
        if ($id <= 0) {
            return $this->json($response, 400, ['error' => 'Invalid contact id']);
        }

        $stmt = $this->pdo->prepare('DELETE FROM contacts WHERE id = :id AND user_id = :uid');
        $stmt->execute([':id' => $id, ':uid' => $user['id']]);

        if ($stmt->rowCount() === 0) {
            return $this->json($response, 404, ['error' => 'Contact not found']);
        }

        return $this->json($response, 200, ['message' => 'Contact deleted']);
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
