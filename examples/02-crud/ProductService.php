<?php

declare(strict_types=1);

namespace StrObj\Examples\Crud;

use InvalidArgumentException;
use OutOfBoundsException;
use StrObj\StringObjects;

/** Uses StrObj for payload selection, defaults, validation and partial updates. */
final class ProductService
{
    /** Writable paths and their creation defaults; other payload fields are ignored. */
    private const FIELDS = [
        'product/name' => null,
        'product/sku' => null,
        'product/pricing/amount' => null,
        'product/pricing/currency' => 'USD',
        'product/inventory/stock' => 0,
        'product/inventory/warehouse/city' => '',
        'product/publication/active' => false,
        'product/description' => null,
    ];

    /** @var ProductRepository */
    private ProductRepository $repository;

    /** Supplies the persistence layer independently of payload processing. */
    public function __construct(ProductRepository $repository)
    {
        $this->repository = $repository;
    }

    /** Creates a product from a JSON request, applying defaults for absent fields. */
    public function create(string $json): array
    {
        $request = StringObjects::instance($json);
        $data = StringObjects::instance(['product' => []]);

        foreach (self::FIELDS as $path => $default) {
            $data->set($path, $request->get($path, $default));
        }

        $candidate = StringObjects::instance($data->toArray(), $this->options());
        $document = $this->validatedDocument($candidate);

        return $this->read($this->repository->create($document));
    }

    /** Reads a product without inspecting its nested inventory or pricing shape. */
    public function read(int $id): array
    {
        $data = StringObjects::instance($this->repository->read($id));

        return ['id' => $id, 'product' => $data->get('product')];
    }

    /**
     * Applies only supplied writable fields, then validates the complete candidate.
     * Existing fields survive an omitted path; explicitly supplied false/0/null survive.
     * Invalid candidates are rejected before the persistence layer is called.
     */
    public function update(int $id, string $json): array
    {
        $candidate = StringObjects::instance($this->repository->read($id), $this->options());
        $patch = StringObjects::instance($json);

        foreach (array_keys(self::FIELDS) as $path) {
            if (!$patch->has($path)) {
                continue;
            }

            $candidate->set($path, $patch->get($path));
        }

        $this->repository->update($id, $this->validatedDocument($candidate));

        return $this->read($id);
    }

    /** Removes a product; deleting an unknown ID raises the same domain error as read. */
    public function delete(int $id): void
    {
        if (!$this->repository->delete($id)) {
            throw new OutOfBoundsException('Product not found: ' . $id);
        }
    }

    /** Returns decoded product records for a list endpoint. */
    public function all(): array
    {
        return array_map(static function (array $record): array {
            $data = StringObjects::instance($record['document']);

            return ['id' => (int) $record['id'], 'product' => $data->get('product')];
        }, $this->repository->all());
    }

    /** Defines validation of stored values and output casts applied after validation. */
    private function options(): array
    {
        return [
            'validation' => ['rules' => [
                ['path' => 'product/name', 'pattern' => '#^.{2,80}$#u', 'required' => true],
                ['path' => 'product/sku', 'pattern' => '#^[A-Z0-9-]{3,30}$#', 'required' => true],
                ['path' => 'product/pricing/amount', 'pattern' => '#^\d+(?:\.\d{1,2})?$#', 'required' => true],
                ['path' => 'product/pricing/currency', 'pattern' => '#^(USD|EUR|GBP)$#', 'required' => true],
                ['path' => 'product/inventory/stock', 'pattern' => '#^\d+$#', 'required' => true],
                ['path' => 'product/inventory/warehouse/city', 'pattern' => '#^.{0,80}$#u'],
                ['path' => 'product/publication/active', 'pattern' => '#^[01]$#', 'required' => true],
                ['path' => 'product/description', 'pattern' => '#^.{0,500}$#su'],
            ]],
            'filters' => [
                'product/pricing/amount' => ['type' => 'float'],
                'product/inventory/stock' => ['type' => 'int'],
                'product/publication/active' => ['type' => 'bool'],
            ],
        ];
    }

    /** Validates current data before casting and serializing only writable fields. */
    private function validatedDocument(StringObjects $candidate): string
    {
        if (!$candidate->isValid()) {
            $invalid = array_filter(array_keys(self::FIELDS), static function (string $path) use ($candidate): bool {
                return !$candidate->isValid($path);
            });
            throw new InvalidArgumentException('Invalid fields: ' . implode(', ', $invalid));
        }

        // toJson() exports stored values. Copy the cast get() values into a separate
        // document so the database also receives the intended types.
        $normalized = StringObjects::instance(['product' => []]);

        foreach (self::FIELDS as $path => $default) {
            $normalized->set($path, $candidate->get($path, $default));
        }

        return $normalized->toJson();
    }
}
