<?php

declare(strict_types=1);

namespace Explorador;

use InvalidArgumentException;

final class VariableCatalog
{
    /** @var array<string,array<string,mixed>> */
    private array $variables;

    public function __construct(?array $variables = null)
    {
        $this->variables = $variables ?? require dirname(__DIR__) . '/catalog/variables.php';
        $this->validate();
        uasort($this->variables, static fn(array $a, array $b): int => $a['order'] <=> $b['order']);
    }

    /** @return array<string,array<string,mixed>> */
    public function all(): array { return $this->variables; }

    /** @return array<string,mixed> */
    public function get(string $key): array
    {
        if (!isset($this->variables[$key])) throw new InvalidArgumentException('Campo desconocido: ' . $key . '.');
        return $this->variables[$key];
    }

    /** @param list<string> $keys @return array<string,array<string,mixed>> */
    public function select(array $keys): array
    {
        $selected = [];
        foreach ($keys as $key) $selected[$key] = $this->get($key);
        return $selected;
    }

    private function validate(): void
    {
        $required = ['key', 'name', 'shortName', 'category', 'description', 'type', 'unit',
            'scaleGroup', 'scope', 'provider', 'dependencies', 'precision', 'order'];
        foreach ($this->variables as $key => $definition) {
            if (!is_string($key) || $key === '' || !is_array($definition) || ($definition['key'] ?? null) !== $key) {
                throw new InvalidArgumentException('El catálogo contiene una clave inválida.');
            }
            foreach ($required as $property) {
                if (!array_key_exists($property, $definition)) throw new InvalidArgumentException('Falta ' . $property . ' en ' . $key . '.');
            }
            if (!is_array($definition['dependencies'])) throw new InvalidArgumentException('Dependencias inválidas en ' . $key . '.');
        }
        foreach ($this->variables as $key => $definition) {
            foreach ($definition['dependencies'] as $dependency) {
                if (!isset($this->variables[$dependency])) throw new InvalidArgumentException('Dependencia desconocida en ' . $key . '.');
            }
        }
    }
}
