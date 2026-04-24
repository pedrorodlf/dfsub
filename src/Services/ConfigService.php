<?php

namespace App\Services;

use GuzzleHttp\Client;
use Sabberworm\CSS\Rule\Rule;
use Sabberworm\CSS\RuleSet\DeclarationBlock;
use Sabberworm\CSS\Value\RuleValueList;
use Sabberworm\CSS\Value\URL;
use Sunra\PhpSimple\HtmlDomParser;
use Symfony\Component\HttpKernel\KernelInterface;

class ConfigService
{
    private string $deployUrl;
    private string $mediaUrl;
    private string $amazonS3EditorBuildUrl;
    private string $appId;
    private int $version;

    private string $previewUrl;
    private string $dbHost;
    private int $dbPort;
    private string $dbName;
    private string $dbUser;
    private string $dbPassword;

    private string $baseUrl = '';
    /**
     * @var false|string[]
     */
    private $allowedQueryParams;

    public function __construct(KernelInterface $kernel)
    {
        $configDistUrl = $kernel->getProjectDir() . '/config/config.json.dist';
        $configDist = json_decode(file_get_contents($configDistUrl), true);

        $configFile = $kernel->getProjectDir() . '/config/config.json';
        if (is_file($configFile)) {
            $configOverride = json_decode(file_get_contents($configFile), true);
            if (is_array($configOverride)) {
                $configDist = array_merge($configDist, $configOverride);
            }
        }

        if (is_file($kernel->getProjectDir() . '/var/application_installed.json')) {
            $applicationConfig = json_decode(file_get_contents($kernel->getProjectDir() . '/var/application_installed.json'), true);
            $this->baseUrl = $applicationConfig['base_url'];
        }

        $this->deployUrl = $configDist['deploy_url'];
        $this->mediaUrl = array_key_exists('media_url', $configDist) ? $configDist['media_url'] : '';
        $this->amazonS3EditorBuildUrl = array_key_exists('amazon_s3_editor_build_url', $configDist) ? $configDist['amazon_s3_editor_build_url'] : '';
        $this->appId = $configDist['app_id'];
        $this->version = $configDist['version'];
        $this->previewUrl = $configDist['preview_url'];
        $this->allowedQueryParams = array_key_exists('allowed_query_params', $configDist) ? explode('|', $configDist['allowed_query_params']) : [];

        $this->dbHost = $configDist['db_host'] ?? '127.0.0.1';
        $this->dbPort = isset($configDist['db_port']) ? (int)$configDist['db_port'] : 3306;
        $this->dbName = $configDist['db_name'] ?? 'dfsub';
        $this->dbUser = $configDist['db_user'] ?? 'root';
        $this->dbPassword = $configDist['db_password'] ?? '';

        try {
            $client = new Client();
            $response = $client->request('GET', $this->deployUrl. '/api/config', [
                'http_errors' => false,
                'headers' => [
                    'Accept' => 'application/json'
                ],
            ]);
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                $remote = json_decode((string) $response->getBody(), true);
                if (is_array($remote)) {
                    $this->allowedQueryParams = explode('|', $remote['allowed_query_params']);
                    $this->mediaUrl = $remote['media_url'];
                    $this->amazonS3EditorBuildUrl = $remote['amazon_s3_editor_build_url'];
                }
            }
        } catch (\Throwable $e) {
        }

    }

    public function getDeployUrl(): string
    {
        return $this->deployUrl;
    }

    public function getMediaUrl(): string
    {
        return $this->mediaUrl;
    }

    public function getAmazonS3EditorBuildUrl(): string
    {
        return $this->amazonS3EditorBuildUrl;
    }

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getPreviewUrl(): string
    {
        return $this->previewUrl;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getDbHost(): string
    {
        return $this->dbHost;
    }

    public function getDbPort(): int
    {
        return $this->dbPort;
    }

    public function getDbName(): string
    {
        return $this->dbName;
    }

    public function getDbUser(): string
    {
        return $this->dbUser;
    }

    public function getDbPassword(): string
    {
        return $this->dbPassword;
    }

    public function getAllowedQueryParams(): array
    {
        return $this->allowedQueryParams;
    }


}