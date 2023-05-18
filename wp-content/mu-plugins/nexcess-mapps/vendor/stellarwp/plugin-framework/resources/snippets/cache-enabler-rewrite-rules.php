<?php

/**
 * Rewrite rules for Cache Enabler by KeyCDN.
 *
 * @link https://www.keycdn.com/support/wordpress-cache-enabler-plugin#apache
 *
 * @var bool   $compress        Whether or not cached pages should be compressed.
 * @var string $excludedCookies A rewrite condition containing the excluded cookie names.
 * @var string $excludedQueries A rewrite condition containing the excluded query string parameters.
 * @var bool   $mobile          Whether or not to cache mobile responses separately.
 * @var bool   $webp            Whether or not images should be converted to webP.
 *
 * We're not enforcing output escaping here, as that can impact the regular expressions.
 * phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
 *
 * Additionally, whitespace here has been optimized for presentation in it rendered form.
 * phpcs:disable Squiz.WhiteSpace.ControlStructureSpacing.SpacingAfterOpen
 */

$permalinkStructure = get_option('permalink_structure', '');

// If we don't have a permalink structure, bail out early.
if (! is_scalar($permalinkStructure)) {
    return '';
}
?>

# Reference: https://www.keycdn.com/support/wordpress-cache-enabler-plugin#apache
<IfModule mod_rewrite.c>
    <IfModule mod_setenvif.c>
        RewriteEngine On
        RewriteBase /

        # cache directory
        SetEnvIf Host ^ CE_CACHE_DIR=/wp-content/cache/cache-enabler

        # default cache keys
        SetEnvIf Host ^ CE_CACHE_KEY_SCHEME http-
        SetEnvIf Host ^ CE_CACHE_KEY_DEVICE
        SetEnvIf Host ^ CE_CACHE_KEY_WEBP
        SetEnvIf Host ^ CE_CACHE_KEY_COMPRESSION

        # scheme cache key
        RewriteCond %{HTTPS} ^(on|1)$ [OR]
        RewriteCond %{SERVER_PORT} =443 [OR]
        RewriteCond %{HTTP:X-Forwarded-Proto} =https [OR]
        RewriteCond %{HTTP:X-Forwarded-Scheme} =https
        RewriteRule ^ - [E=CE_CACHE_KEY_SCHEME:https-]

    <?php if ($mobile) : ?>

        # device cache key
        SetEnvIf User-Agent "(Mobile|Android|Silk/|Kindle|BlackBerry|Opera Mini|Opera Mobi)" CE_CACHE_KEY_DEVICE=-mobile

    <?php endif; ?>

    <?php if ($webp) : ?>

        # webp cache key
        SetEnvIf Accept image/webp CE_CACHE_KEY_WEBP=-webp

    <?php endif; ?>

    <?php if ($compress) : ?>

        # compression cache key
        <IfModule mod_mime.c>
            SetEnvIf Accept-Encoding gzip CE_CACHE_KEY_COMPRESSION=.gz
            AddType text/html .gz
            AddEncoding gzip .gz
        </IfModule>

    <?php endif; ?>

        # get cache file
        SetEnvIf Host ^ CE_CACHE_FILE_DIR=%{ENV:CE_CACHE_DIR}/%{HTTP_HOST}%{REQUEST_URI}
        SetEnvIf Host ^ CE_CACHE_FILE_NAME=%{ENV:CE_CACHE_KEY_SCHEME}index%{ENV:CE_CACHE_KEY_DEVICE}%{ENV:CE_CACHE_KEY_WEBP}.html%{ENV:CE_CACHE_KEY_COMPRESSION}
        SetEnvIf Host ^ CE_CACHE_FILE=%{ENV:CE_CACHE_FILE_DIR}/%{ENV:CE_CACHE_FILE_NAME}

        # check if cache file exists
        RewriteCond %{DOCUMENT_ROOT}%{ENV:CE_CACHE_FILE} -f

        # check request method
        RewriteCond %{REQUEST_METHOD} =GET

    <?php if ('/' === mb_substr((string) $permalinkStructure, -1, 1)) : ?>

        # check permalink structure has trailing slash
        RewriteCond %{REQUEST_URI} /[^\./\?]+(\?.*)?$

    <?php else : ?>

        # check permalink structure has no trailing slash
        # RewriteCond %{REQUEST_URI} /[^\./\?]+/(\?.*)?$

    <?php endif; ?>

    <?php if (! empty($excludedQueries)) : ?>

        # check excluded query strings
        RewriteCond %{QUERY_STRING} !<?php echo $excludedQueries; ?>

    <?php endif; ?>

    <?php if (! empty($excludedCookies)) : ?>

        # check excluded cookies
        RewriteCond %{HTTP_COOKIE} !<?php echo $excludedCookies; ?>

    <?php endif; ?>

        # deliver cache file
        RewriteRule ^ %{ENV:CE_CACHE_FILE} [L]
    </IfModule>
</IfModule>
