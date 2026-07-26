<?php

declare(strict_types=1);

namespace TCT\Compatibility\Doctor;

final class DoctorContext
{
    /**
     * @param list<ProviderSignal> $providerSignals
     */
    public function __construct(
        public readonly string $homeRootUrl,
        public readonly string $sitemapUrl,
        public readonly string $pluginVersion,
        public readonly string $routeSignature,
        public readonly string $externalValidatorCommand,
        public readonly bool $publicResourcesEnabled = true,
        public readonly array $providerSignals = []
    ) {
        if (
            SameOriginUrl::origin($homeRootUrl) === null
            || !SameOriginUrl::isSame($homeRootUrl, $sitemapUrl)
        ) {
            throw new \InvalidArgumentException('Doctor context URLs must share one valid HTTP origin.');
        }

        foreach ($providerSignals as $signal) {
            if (!$signal instanceof ProviderSignal) {
                throw new \InvalidArgumentException('Doctor provider signals are invalid.');
            }
        }
    }
}
