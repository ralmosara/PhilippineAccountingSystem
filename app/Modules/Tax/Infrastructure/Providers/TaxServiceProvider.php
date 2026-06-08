<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Providers;

use App\Modules\Procurement\Application\Contracts\AtcCodeProviderContract;
use App\Modules\Tax\Application\Contracts\AnnualIncomeTaxAggregatorContract;
use App\Modules\Tax\Application\Contracts\BirFormRepositoryContract;
use App\Modules\Tax\Application\Contracts\EisCertificateLoaderContract;
use App\Modules\Tax\Application\Contracts\EisGatewayClientContract;
use App\Modules\Tax\Application\Contracts\EisPayloadSignerContract;
use App\Modules\Tax\Application\Contracts\FormDataAggregatorContract;
use App\Modules\Tax\Application\Contracts\PdfRendererContract;
use App\Modules\Tax\Application\Contracts\SellerProfileProviderContract;
use App\Modules\Tax\Domain\Services\Eis\JsonCanonicalizer;
use App\Modules\Tax\Infrastructure\Eis\EloquentSellerProfileProvider;
use App\Modules\Tax\Infrastructure\Eis\FakeEisGatewayClient;
use App\Modules\Tax\Infrastructure\Eis\HttpEisGatewayClient;
use App\Modules\Tax\Infrastructure\Eis\P12CertificateLoader;
use App\Modules\Tax\Infrastructure\Eis\Pkcs7DetachedSigner;
use App\Modules\Tax\Infrastructure\Pdf\DomPdfRenderer;
use App\Modules\Tax\Application\Contracts\Form2307ReceivedRepositoryContract;
use App\Modules\Tax\Application\Contracts\OsdElectionRepositoryContract;
use App\Modules\Tax\Infrastructure\Persistence\EloquentAnnualIncomeTaxAggregator;
use App\Modules\Tax\Infrastructure\Persistence\EloquentAtcCodeProvider;
use App\Modules\Tax\Infrastructure\Persistence\EloquentBirFormRepository;
use App\Modules\Tax\Infrastructure\Persistence\EloquentForm2307ReceivedRepository;
use App\Modules\Tax\Infrastructure\Persistence\EloquentFormDataAggregator;
use App\Modules\Tax\Infrastructure\Persistence\EloquentOsdElectionRepository;
use Illuminate\Contracts\Http\Client\Factory as HttpFactoryContract;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

final class TaxServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        BirFormRepositoryContract::class              => EloquentBirFormRepository::class,
        AtcCodeProviderContract::class                => EloquentAtcCodeProvider::class,
        PdfRendererContract::class                    => DomPdfRenderer::class,
        JsonCanonicalizer::class                      => JsonCanonicalizer::class,
        Form2307ReceivedRepositoryContract::class     => EloquentForm2307ReceivedRepository::class,
        OsdElectionRepositoryContract::class          => EloquentOsdElectionRepository::class,
    ];

    public function register(): void
    {
        $this->app->singleton(
            FormDataAggregatorContract::class,
            fn ($app) => new EloquentFormDataAggregator($app->make(ConnectionInterface::class))
        );

        $this->app->singleton(
            AnnualIncomeTaxAggregatorContract::class,
            fn ($app) => new EloquentAnnualIncomeTaxAggregator($app->make(ConnectionInterface::class))
        );

        $this->registerEisBindings();
    }

    /**
     * EIS bindings — when BIR_EIS_ENABLED=false the gateway is a deterministic
     * fake so dev/staging exercise the full pipeline without hitting BIR.
     * Signer + cert loader still bind to the real implementations so a missing
     * cert during dev fails loudly the moment EIS is enabled.
     */
    private function registerEisBindings(): void
    {
        $this->app->singleton(
            SellerProfileProviderContract::class,
            fn ($app) => new EloquentSellerProfileProvider($app->make(ConnectionInterface::class))
        );

        $this->app->singleton(
            EisCertificateLoaderContract::class,
            fn ($app) => new P12CertificateLoader(
                p12Path:    (string) config('services.bir_eis.cert_path'),
                passphrase: (string) config('services.bir_eis.cert_passphrase', ''),
            ),
        );

        $this->app->singleton(
            EisPayloadSignerContract::class,
            fn ($app) => new Pkcs7DetachedSigner(
                certLoader:    $app->make(EisCertificateLoaderContract::class),
                canonicalizer: $app->make(JsonCanonicalizer::class),
            ),
        );

        $this->app->singleton(EisGatewayClientContract::class, function ($app) {
            $enabled = (bool) config('services.bir_eis.enabled', false);

            if (! $enabled) {
                return new FakeEisGatewayClient();
            }

            return new HttpEisGatewayClient(
                http:            $app->make(HttpFactory::class),
                baseUrl:         (string) config('services.bir_eis.base_url'),
                bearerToken:     (string) config('services.bir_eis.bearer_token', ''),
                timeoutSeconds:  (int)    config('services.bir_eis.timeout', 30),
                issuancePath:    (string) config('services.bir_eis.issuance_path', '/api/v1/invoices'),
            );
        });

        // Also expose the fake under its concrete class for test injection
        $this->app->singleton(FakeEisGatewayClient::class);
    }

    public function boot(): void
    {
        // Listeners on BirFormGenerated/Filed live in their respective modules
        // (e.g. notify finance team) and register themselves there.
    }
}
