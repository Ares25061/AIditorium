<?php

namespace App\AI\Services;

class AIModelResolver
{
    /**
     * @return array<int, string>
     */
    public function allowedKeys(): array
    {
        return array_keys($this->configuredModels());
    }

    public function defaultKey(): string
    {
        $configuredDefault = (string) config('ai.default_model_key', 'minimax');

        return array_key_exists($configuredDefault, $this->configuredModels())
            ? $configuredDefault
            : 'minimax';
    }

    public function normalizeKey(?string $key): string
    {
        $key = trim((string) $key);

        return array_key_exists($key, $this->configuredModels())
            ? $key
            : $this->defaultKey();
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function publicOptions(): array
    {
        $options = [];
        foreach ($this->configuredModels() as $key => $model) {
            $options[] = [
                'key' => (string) $key,
                'label' => (string) ($model['label'] ?? $key),
            ];
        }

        return $options;
    }

    /**
     * @return array{key: string, label: string, provider: string, base_url: string, api_key: string, model: string}
     */
    public function resolve(?string $key): array
    {
        $normalizedKey = $this->normalizeKey($key);
        $models = $this->configuredModels();

        return $this->normalizeModel($normalizedKey, $models[$normalizedKey] ?? []);
    }

    /**
     * @return array{key: string, label: string, provider: string, base_url: string, api_key: string, model: string}
     */
    public function resolveProviderModel(?string $provider, ?string $model): array
    {
        $provider = trim((string) $provider);
        $model = trim((string) $model);

        foreach ($this->configuredModels() as $key => $configuredModel) {
            $resolved = $this->normalizeModel((string) $key, $configuredModel);
            if ($resolved['provider'] === $provider && $resolved['model'] === $model) {
                return $resolved;
            }
        }

        if ($provider === '' || $model === '') {
            return $this->resolve(null);
        }

        return [
            'key' => $provider,
            'label' => $model,
            'provider' => $provider,
            'base_url' => $this->fallbackBaseUrl($provider),
            'api_key' => $this->fallbackApiKey($provider),
            'model' => $model,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function configuredModels(): array
    {
        $models = config('ai.models', []);

        return is_array($models) ? $models : [];
    }

    /**
     * @param array<string, mixed> $model
     * @return array{key: string, label: string, provider: string, base_url: string, api_key: string, model: string}
     */
    private function normalizeModel(string $key, array $model): array
    {
        $provider = trim((string) ($model['provider'] ?? config('ai.provider', 'openrouter')));
        $modelId = trim((string) ($model['model'] ?? config('ai.model', '')));

        return [
            'key' => $key,
            'label' => (string) ($model['label'] ?? $key),
            'provider' => $provider !== '' ? $provider : 'openrouter',
            'base_url' => rtrim((string) ($model['base_url'] ?? ''), '/') ?: $this->fallbackBaseUrl($provider),
            'api_key' => (string) ($model['api_key'] ?? '') ?: $this->fallbackApiKey($provider),
            'model' => $modelId !== '' ? $modelId : (string) config('ai.model', 'minimax/minimax-m2.5:free'),
        ];
    }

    private function fallbackBaseUrl(string $provider): string
    {
        if ($provider === 'nekocode') {
            return rtrim((string) config('ai.nekocode.base_url', 'https://gateway.nekocode.app/andromeda/v1'), '/');
        }

        return rtrim((string) config('ai.base_url', 'https://openrouter.ai/api/v1'), '/');
    }

    private function fallbackApiKey(string $provider): string
    {
        if ($provider === 'nekocode') {
            return (string) config('ai.nekocode.api_key', '');
        }

        return (string) config('ai.api_key', '');
    }
}
