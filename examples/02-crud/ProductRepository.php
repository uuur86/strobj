<?php

declare(strict_types=1);

namespace StrObj\Examples\Crud;

use OutOfBoundsException;
use PDO;

/** Persists product JSON documents with prepared SQLite statements. */
final class ProductRepository
{
    /** @var PDO */
    private PDO $database;

    /** Creates the example's table in the supplied SQLite connection. */
    public function __construct(PDO $database)
    {
        $this->database = $database;
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec(
            'CREATE TABLE IF NOT EXISTS products (id INTEGER PRIMARY KEY AUTOINCREMENT, document TEXT NOT NULL)'
        );
    }

    /** Stores a document and returns the generated product ID. */
    public function create(string $document): int
    {
        $statement = $this->database->prepare('INSERT INTO products (document) VALUES (:document)');
        $statement->execute(['document' => $document]);

        return (int) $this->database->lastInsertId();
    }

    /** @throws OutOfBoundsException When the product does not exist. */
    public function read(int $id): string
    {
        $statement = $this->database->prepare('SELECT document FROM products WHERE id = :id');
        $statement->execute(['id' => $id]);
        $document = $statement->fetchColumn();

        if ($document === false) {
            throw new OutOfBoundsException('Product not found: ' . $id);
        }

        return $document;
    }

    /** Replaces an existing product document after the service validates it. */
    public function update(int $id, string $document): void
    {
        $statement = $this->database->prepare('UPDATE products SET document = :document WHERE id = :id');
        $statement->execute(['id' => $id, 'document' => $document]);
    }

    /** Deletes a product and reports whether a record was removed. */
    public function delete(int $id): bool
    {
        $statement = $this->database->prepare('DELETE FROM products WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() === 1;
    }

    /** Returns the remaining records in insertion order. */
    public function all(): array
    {
        return $this->database->query('SELECT id, document FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }
}
