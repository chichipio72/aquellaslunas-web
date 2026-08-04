<?php

declare(strict_types=1);

namespace Explorador;

final class SeriesPlanner
{
    public function __construct(private readonly VariableCatalog $catalog) {}

    /** @param list<string> $selected @return array{selected:list<string>,required:list<string>,providers:list<string>} */
    public function plan(array $selected): array
    {
        $required = [];
        $visit = function (string $key) use (&$visit, &$required): void {
            if (isset($required[$key])) return;
            $definition = $this->catalog->get($key);
            foreach ($definition['dependencies'] as $dependency) $visit($dependency);
            $required[$key] = true;
        };
        foreach ($selected as $key) $visit($key);
        $providers = [];
        foreach (array_keys($required) as $key) {
            $provider = $this->catalog->get($key)['provider'];
            if ($provider !== 'derived') $providers[$provider] = true;
        }
        return ['selected' => array_values($selected), 'required' => array_keys($required), 'providers' => array_keys($providers)];
    }
}
