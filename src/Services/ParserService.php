<?php

namespace App\Services;

use Sabberworm\CSS\Rule\Rule;
use Sabberworm\CSS\RuleSet\DeclarationBlock;
use Sabberworm\CSS\Value\RuleValueList;
use Sabberworm\CSS\Value\URL;
use Sunra\PhpSimple\HtmlDomParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;

class ParserService
{
    const FONTS_CSS_FILE = '/var/assets/fonts/';
    const PLACEHOLDER_NEW_LINE = '###NL###';

    private KernelInterface $kernel;
    private ConfigService $configService;
    private Request $request;

    private $base_path_prefix = '/var/assets';

    public function __construct(
        KernelInterface $kernel,
        ConfigService   $configService,
        RequestStack    $requestStack
    )
    {
        $this->kernel = $kernel;
        $this->configService = $configService;
        $this->request = $requestStack->getCurrentRequest();
    }

    // --- NOVO MÉTODO: Download robusto com simulação de Navegador e correção de URL ---
    private function fetchUrl(string $url)
    {
        if (strpos($url, 'http') !== 0) {
            if (strpos($url, '//') === 0) {
                $url = 'https:' . $url;
            } else {
                $previewUrl = rtrim($this->configService->getPreviewUrl(), '/');
                $url = $previewUrl . '/' . ltrim($url, '/');
            }
        }
        
        $url = str_replace(' ', '%20', $url);

        try {
            $context = stream_context_create([
                "http" => [
                    "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n",
                    "follow_location" => 1,
                    "max_redirects" => 5
                ],
                "ssl" => [
                    "verify_peer" => false,
                    "verify_peer_name" => false,
                ]
            ]);
            return @file_get_contents($url, false, $context);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function parseHtml(string $html)
    {
        $currentUrl = $this->request->getScheme() . '://' . $this->request->getHost();
        $placeholderNewLine = self::PLACEHOLDER_NEW_LINE;
        $html = str_replace("\n", $placeholderNewLine, $html);
        $dom = HtmlDomParser::str_get_html($html);
        
        // --- INJEÇÃO DE FAVICONS E META TAGS ---
        // Isso garante que o celular reconheça o ícone e a escala da página
        $headTags = '
        <meta name="theme-color" content="#5591c2">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="DFSub">
        <meta name="application-name" content="DFSub">
        <meta name="msapplication-TileColor" content="#5591c2">
        <meta name="msapplication-config" content="/var/assets/img/browserconfig.xml">
        <title>DFSub | Associação</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <link rel="icon" href="/var/assets/img/cropped-favicon-32x32.png" sizes="32x32" />
        <link rel="icon" href="/var/assets/img/cropped-favicon-192x192.png" sizes="192x192" />
        <link rel="apple-touch-icon" href="/var/assets/img/cropped-favicon-180x180.png" />';

        $head = $dom->find('head', 0);
        if ($head) {
            $head->innertext = $headTags . $head->innertext;
        }

        $data = $this->modifyLinks($dom);
        $this->saveAssets($data);

        $html = (string)$dom;
        $html = str_replace($placeholderNewLine, "\n", $html);
        $html = str_replace($this->configService->getPreviewUrl(), $currentUrl, $html);
        
        $previewUrl = rtrim($this->configService->getPreviewUrl(), '/');
        $html = str_replace('###PREVIEW_URL###', $previewUrl, $html);

        // Botão flutuante ajustado para mobile (um pouco menor para não tampar conteúdo)
        $loginButton = '
        <a href="/login" style="position: fixed; bottom: 20px; right: 20px; z-index: 99999; background-color: #1f2f64; color: #ffffff; padding: 10px 20px; border-radius: 50px; font-family: \'Inter\', sans-serif; font-weight: 600; text-decoration: none; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3); display: flex; align-items: center; gap: 10px; border: 2px solid rgba(255,255,255,0.1); font-size: 14px;">
            <img src="/var/assets/img/cropped-favicon-32x32.png" style="width: 20px; height: 20px; border-radius: 50%;"> 
            Área do Associado
        </a>';
        
        if (stripos($html, '</body>') !== false) {
            $html = str_ireplace('</body>', $loginButton . "\n</body>", $html);
        } else {
            $html .= $loginButton;
        }

        return str_replace("{{ site_url }}", $currentUrl, $html);
    }

    private function saveAssets($assets)
    {
        foreach ($assets as $row) {
            if (false !== $hash_pos = strpos($row['new_name'], '#')) {
                $row['new_name'] = substr($row['new_name'], 0, $hash_pos);
            }

            $assetsDir = $this->kernel->getProjectDir() . '/' . $row['dirname'];
            $this->makeDir($assetsDir);

            $saveFilePath = $assetsDir . '/' . $row['new_name'];
            if (file_exists($saveFilePath))
                continue;

            // Usa o novo método de download seguro
            $content = $this->fetchUrl($row['link']);
            if ($content) {
                @file_put_contents($saveFilePath, $content);
            }
        }
    }

    private function saveContentOfResource($content, $row)
    {
        $assetsDir = $this->kernel->getProjectDir() . '/' . $row['dirname'];
        $this->makeDir($assetsDir);
        $saveFilePath = $assetsDir . '/' . $row['new_name'];
        
        if (file_exists($saveFilePath))
            return;

        try {
            @file_put_contents($saveFilePath, $content);
        } catch (\Throwable $e) {}
    }

    private function makeDir($path)
    {
        return is_dir($path) || mkdir($path, 0777, true);
    }

    protected function modifyLinks($dom)
    {
        $currentUrl = $this->request->getScheme() . '://' . $this->request->getHost();
        $result = [];

        foreach ($this->getTags() as $tag => $attr_names) {
            foreach ($attr_names as $one_attr_name) {
                foreach ($dom->find($tag) as $row) {

                    if($tag == 'div'){
                        if(isset($row->attr[$one_attr_name])){
                            $cssStyle = '.template_class {'.$row->attr[$one_attr_name].'}';
                            $css = $this->replaceCss($cssStyle);

                            $cssModified = $css['css'];
                            $result = array_merge($result, $css['cache']);
                            $cssModified = str_replace('.template_class {','',$cssModified);
                            $cssModified = str_replace('}','',$cssModified);
                            $cssModified = str_replace('url("',"url('",$cssModified);
                            $cssModified = str_replace('")',"')",$cssModified);
                            $row->attr[$one_attr_name] = $cssModified;
                        }
                    }

                    if ($tag == 'style') {
                        $css = $this->replaceCss(str_replace(self::PLACEHOLDER_NEW_LINE, "\n", $row->innertext()));
                        $row->__set('innertext', $css['css']);
                        $result = array_merge($result, $css['cache']);
                        continue;
                    }
                    if (!isset($row->attr[$one_attr_name])) {
                        continue;
                    }

                    if ($tag == 'link') {
                        if($row->attr[$one_attr_name] == 'canonical') {
                            $row->attr['href'] = $currentUrl . $this->request->getPathInfo();
                        }

                        if (str_contains($row->attr[$one_attr_name], 'fonts/style.css')) {
                            $fontsCssPath = $this->saveFontsCssFile($row->attr[$one_attr_name]);
                            $row->attr[$one_attr_name] = $fontsCssPath;
                            continue;
                        }

                        if (str_contains($row->attr[$one_attr_name], 'https://fonts.bunny.net/css')) {
                            $fontsCssPath = $this->saveFontsCssFile($row->attr[$one_attr_name]);
                            $row->attr[$one_attr_name] = $fontsCssPath;
                            continue;
                        }

                        if (str_contains($row->attr[$one_attr_name], 'https://fonts.bunny.net/')) {
                            $row->attr[$one_attr_name] = $currentUrl;
                            continue;
                        }

                        if (str_contains($row->attr[$one_attr_name], '//fonts.bunny.net')) {
                            $row->attr[$one_attr_name] = '//' . $this->request->getHost();
                            continue;
                        }
                    }

                    if (strpos($row->attr[$one_attr_name], $this->configService->getDeployUrl()) === false &&
                        strpos($row->attr[$one_attr_name], $this->configService->getMediaUrl()) === false &&
                        strpos($row->attr[$one_attr_name], $this->configService->getAmazonS3EditorBuildUrl()) === false &&
                        strpos($row->attr[$one_attr_name], $this->configService->getPreviewUrl()) === false
                    ) {
                        continue;
                    }

                    if ($tag == 'a' && !preg_match('/(png|jpg|jpeg|gif)$/', $row->attr[$one_attr_name])) {
                        if (strpos($row->attr[$one_attr_name], '/', 0) == 0) {
                            $row->attr[$one_attr_name] = substr($row->attr[$one_attr_name], 1);
                        }
                        continue;
                    }

                    if ($tag == 'link' && !preg_match('/\.css|\.png|\.jpg|\.jpeg/', $row->attr[$one_attr_name])) {
                        continue;
                    }

                    if ($tag == 'meta' && !filter_var($row->attr[$one_attr_name], FILTER_VALIDATE_URL)) {
                        continue;
                    }

                    $row->attr[$one_attr_name] = htmlspecialchars_decode($row->attr[$one_attr_name]);
                    $path = $this->getPathFromUrl($row->attr[$one_attr_name]);

                    if (is_array($path)) {
                        $new_link = '';
                        foreach ($path as $i => $one_path) {
                            $normalizedUrl = $this->normalizeUrl($row->attr[$one_attr_name]);
                            $one_path = preg_replace("/\s(.+)$/", "", trim($one_path));

                            [$path_parts, $resourceContent] = $this->handleFileExtension(pathinfo($one_path), $normalizedUrl[$i]);
                            $asset_path = $this->getDirNameByContentType($path_parts);
                            $extension = preg_replace("/\#(.+)$/", "", $path_parts['extension'] ?? 'bin');
                            
                            $filename = $path_parts['filename'] ?? 'asset_' . uniqid();
                            
                            $assetInfo = [
                                'link' => $normalizedUrl[$i],
                                'dirname' => $asset_path . ($path_parts['dirname'] ?? ''),
                                'basename' => preg_replace("/\#(.+)$/", "", $path_parts['basename'] ?? ''),
                                'extension' => $extension,
                                'filename' => $filename,
                                'new_name' => $filename . "." . $extension,
                                'tag' => $tag
                            ];

                            if ($resourceContent)
                                $this->saveContentOfResource($resourceContent, $assetInfo);

                            $result[] = $assetInfo;
                            $zoom = $i + 1;
                            $new_link .= $this->configService->getBaseUrl() . $asset_path . ($path_parts['dirname'] ?? '') . "/" . $filename . "." . $extension . " {$zoom}x";
                            if ($zoom == 1) {
                                $new_link .= ", ";
                            }
                        }
                        $row->attr[$one_attr_name] = trim($new_link);
                    } else {
                        [$path_parts, $resourceContent] = $this->handleFileExtension(pathinfo($path), $row->attr[$one_attr_name]);
                        $asset_path = $this->getDirNameByContentType($path_parts);
                        $extension = preg_replace("/\#(.+)$/", "", $path_parts['extension'] ?? 'bin');
                        $link = htmlspecialchars_decode($row->attr[$one_attr_name]);
                        $filename = $path_parts['filename'] ?? 'asset_' . uniqid();

                        if ($path == '/fonts/style.css') {
                            $cssContent = $this->fetchUrl($link);
                            if ($cssContent) {
                                $css = $this->replaceCss($cssContent);
                                $link = $css['css'];
                                foreach ($css['cache'] as $key => $css_value) {
                                    $asset_name = $css_value['dirname'] . "/" . $css_value['new_name'];
                                    $link = str_replace($asset_name, '../' . $asset_name, $link);
                                }
                                $result = array_merge($result, $css['cache']);
                            }
                        }

                        $assetInfo = [
                            'link' => $link,
                            'dirname' => $asset_path . ($path_parts['dirname'] ?? ''),
                            'basename' => preg_replace("/\#(.+)$/", "", $path_parts['basename'] ?? ''),
                            'extension' => $extension,
                            'filename' => $filename,
                            'new_name' => $filename . "." . $extension,
                            'tag' => $tag
                        ];

                        if ($resourceContent)
                            $this->saveContentOfResource($resourceContent, $assetInfo);

                        $result[] = $assetInfo;

                        $row->attr[$one_attr_name] = $this->configService->getBaseUrl() . $asset_path . ($path_parts['dirname'] ?? '') . "/" . $filename . "." . $extension;
                    }
                }
            }
        }

        return $result;
    }

    static function getPageAssetsPath($pathInfo)
    {
        return '';
    }

    protected function replaceCss($css)
    {
        $oCssParser = new \Sabberworm\CSS\Parser($css);
        try {
            $oCssDocument = $oCssParser->parse();
        } catch (\Exception $e) {
            return ['cache' => [], 'css' => $css];
        }
        $result = [];

        foreach ($oCssDocument->getAllRuleSets() as $oRuleSet) {
            foreach ($oRuleSet->getRules() as $rule) {
                $rules = $this->getUrlFromCssRuleValues($rule->getValue());

                if (!count($rules)) {
                    continue;
                }

                foreach ($rules as $ruleValue) {
                    $url = str_replace(' ', ' ',$ruleValue->getUrl()->getString());
                    if (!filter_var($url, FILTER_VALIDATE_URL) && strpos($url, '/') !== 0 && strpos($url, '../') !== 0) {
                        continue;
                    }

                    $prefix1 = 'url("';
                    $prefix2 = '")';

                    $path = $this->getPathFromUrl($url);
                    $path = str_replace($prefix1, '', str_replace($prefix2, '', $path));
                    [$path_parts, $resourceContent] = $this->handleFileExtension(pathinfo($path), $url);
                    $asset_path = $this->getDirNameByContentType($path_parts);
                    
                    $filename = $path_parts['filename'] ?? 'asset_' . uniqid();
                    $extension = $path_parts['extension'] ?? 'bin';
                    
                    $assetInfo = [
                        'link' => str_replace($prefix1, '', str_replace($prefix2, '', $url)),
                        'dirname' => $asset_path . ($path_parts['dirname'] ?? ''),
                        'basename' => preg_replace("/\#(.+)$/", "", $path_parts['basename'] ?? ''),
                        'extension' => $extension,
                        'filename' => $filename,
                        'new_name' => $filename . "." . $extension
                    ];

                    if ($resourceContent)
                        $this->saveContentOfResource($resourceContent, $assetInfo);

                    $result[] = $assetInfo;
                    $value = str_replace($url, $this->configService->getBaseUrl() . $asset_path . ($path_parts['dirname'] ?? '') . "/" . $filename . "." . $extension, $rule->getValue());
                    $rule->setValue($value);
                }
            }
        }
        return [
            'cache' => $result,
            'css' => $oCssDocument->render()
        ];
    }

    private function getUrlFromCssRuleValues($ruleValue)
    {
        $result = [];
        if ($ruleValue instanceof URL) {
            return [$ruleValue];
        } elseif ($ruleValue instanceof RuleValueList) {
            foreach ($ruleValue->getListComponents() as $component) {
                if ($component instanceof RuleValueList) {
                    $result = array_merge($result, $this->getUrlFromCssRuleValues($component));
                } elseif ($component instanceof URL) {
                    $result = array_merge($result, [$component]);
                } else {
                    continue;
                }
            }
        }
        return $result;
    }

    protected function getDirNameByContentType($path_parts)
    {
        $ext = preg_replace("/\#(.+)$/", "", $path_parts['extension'] ?? '');
        if (in_array($ext, ['png', 'jpg', 'gif', 'jpeg'])) {
            return $this->base_path_prefix . $this->getPageAssetsPath($this->request->getPathInfo()) . '/img';
        } elseif (in_array($ext, ['svg'])) {
            return $this->base_path_prefix . $this->getPageAssetsPath($this->request->getPathInfo()) . '/svg';
        } elseif (in_array($ext, ['ttf', 'eot', 'woff', 'woff2'])) {
            return '/var/fonts';
        }
        return $this->base_path_prefix . $this->getPageAssetsPath($this->request->getPathInfo());
    }

    private function handleFileExtension($path_parts, $url)
    {
        $buffer = null;
        if (!isset($path_parts['extension'])) {
            $buffer = $this->fetchUrl($url);
            if ($buffer) {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mime = explode('/', $finfo->buffer($buffer));
                $path_parts['extension'] = $mime[1] ?? 'bin';
            } else {
                $path_parts['extension'] = 'bin';
            }
        }
        return [$path_parts, $buffer];
    }

    private function getPathFromUrl($url)
    {
        $url = preg_replace('/(http|https):\/\/(([\w-]+\.)+[\w-]+)/', '', $url);
        $urls = explode(',', $url);
        $urls = $this->trimQueryStringFromUrls($urls);
        if (count($urls) == 1) {
            return $urls[0];
        }
        return $urls;
    }

    private function trimQueryStringFromUrls($urls)
    {
        foreach ($urls as $i => $url) {
            $urls[$i] = preg_replace('/\?.*/', '', $url);
        }
        return $urls;
    }

    private function normalizeUrl($url)
    {
        $urls = explode(',', $url);
        foreach ($urls as $i => $row) {
            $row = preg_replace("/(.*)\/(.*)\.(.*)$/", "$1/image.$3", $row);
            $urls[$i] = htmlspecialchars_decode(preg_replace("/\s(.+)$/", "", trim($row)));
        }
        if (count($urls) == 1) {
            return $urls[0];
        }
        return $urls;
    }

    protected function getTags()
    {
        return [
            'div' => ['style'],
            'script' => ['src'],
            'link' => ['href','rel'],
            'use' => ['xlink:href', 'href'],
            'img' => ['src', 'srcset'],
            'source' => ['srcset'],
            'style' => [null],
            'a' => ['href'],
        ];
    }

    public function removeNotAllowedQueryParams()
    {
        $queryNameList = array_keys($this->request->query->all());
        $queriesToBeDeleted = array_diff($queryNameList, $this->configService->getAllowedQueryParams());

        $this->request->query->add(['native_url' => 1]);

        if (!count($queriesToBeDeleted)) {
            $this->request->overrideGlobals();
            return;
        }

        foreach (array_values($queriesToBeDeleted) as $queryParam) {
            $this->request->query->remove($queryParam);
        }

        $this->request->overrideGlobals();
    }

    private function saveFontsCssFile($fontsBunnyUrl)
    {
        $fontsBunnyUrlParsed = parse_url($fontsBunnyUrl);
        $fontsCssFilePath = $this->configService->getBaseUrl() . self::FONTS_CSS_FILE . 'fonts_' . md5($fontsBunnyUrlParsed['query'] ?? '') . '.css';

        if (file_exists($this->kernel->getProjectDir() . $fontsCssFilePath)) {
            return $fontsCssFilePath;
        }

        $query = explode('&', $fontsBunnyUrlParsed['query'] ?? '');
        foreach ($query as $j => $value) {
            $value = explode('=', $value, 2);
            if (count($value) == 2)
                $query[$j] = urlencode($value[0]) . '=' . urlencode($value[1]);
            else
                $query[$j] = urlencode($value[0]);
        };

        $queryParams = implode('&', $query);
        $scheme = $fontsBunnyUrlParsed['scheme'] ?? 'https';
        $host = $fontsBunnyUrlParsed['host'] ?? 'fonts.bunny.net';
        $path = $fontsBunnyUrlParsed['path'] ?? '/css';
        
        $normalizedUrl = $scheme . '://' . $host . $path . '?' . $queryParams;

        $this->makeDir($this->kernel->getProjectDir() . $this->configService->getBaseUrl() . self::FONTS_CSS_FILE);
        
        $cssContent = $this->fetchUrl($normalizedUrl);
        if ($cssContent) {
            $css = $this->replaceCss($cssContent);
            $this->saveAssets($css['cache']);
            @file_put_contents($this->kernel->getProjectDir() . $fontsCssFilePath, $css['css']);
        }

        return $fontsCssFilePath;
    }
}