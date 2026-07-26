<?php

declare(strict_types=1);

namespace TCT\Compatibility\Doctor;

final class DoctorReport
{
    public const SCHEMA = 'tct-deployment-doctor-report-v1';
    public const IMPLEMENTATION_VERSION = 'checkpoint1-v1';

    /**
     * @param array<string, int|float> $counters
     * @param array<string, LayerResult> $layers
     * @param list<DoctorCheck> $checks
     * @param list<ProviderSignal> $signals
     */
    public function __construct(
        public readonly string $pluginVersion,
        public readonly string $routeSignature,
        public readonly string $homeOrigin,
        public readonly string $sitemapUrl,
        public readonly string $startedAt,
        public readonly string $completedAt,
        public readonly DoctorLimits $limits,
        public readonly array $counters,
        public readonly array $layers,
        public readonly array $checks,
        public readonly array $signals,
        public readonly string $externalValidatorCommand
    ) {
    }

    public function publicOutcome(): Outcome
    {
        return $this->layers['public_delivery_path']->outcome ?? Outcome::Inconclusive;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema' => self::SCHEMA,
            'doctor_version' => self::IMPLEMENTATION_VERSION,
            'plugin_version' => BoundedText::utf8($this->pluginVersion, 128),
            'route_signature' => BoundedText::identifier($this->routeSignature),
            'home_origin' => BoundedText::utf8($this->homeOrigin, 2048),
            'sitemap_url' => BoundedText::utf8($this->sitemapUrl, 2048),
            'started_at' => BoundedText::utf8($this->startedAt, 64),
            'completed_at' => BoundedText::utf8($this->completedAt, 64),
            'overall' => $this->publicOutcome()->value,
            'limits' => $this->limits->toArray(),
            'counters' => $this->counters,
            'layers' => array_map(
                static fn(LayerResult $layer): array => $layer->toArray(),
                $this->layers
            ),
            'checks' => array_map(
                static fn(DoctorCheck $check): array => $check->toArray(),
                $this->checks
            ),
            'signals' => array_map(
                static fn(ProviderSignal $signal): array => $signal->toArray(),
                $this->signals
            ),
            'external_validator_command' => BoundedText::utf8(
                $this->externalValidatorCommand,
                4096
            ),
        ];
    }
}
