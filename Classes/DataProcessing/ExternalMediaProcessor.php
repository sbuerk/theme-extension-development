<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\DataProcessing;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Turns the URL an editor pasted into the "theme_external_media" element into
 * the address of a privacy friendly embed, or into nothing at all.
 *
 * The element never loads anything by itself - the placeholder it renders is a
 * poster and a button, and only a press on that button inserts an iframe (see
 * "Templates/ContentElements/ThemeExternalMedia.html" and the embed part of
 * "Resources/Public/JavaScript/theme.js"). What this processor decides is
 * *which* address that iframe would get, which is a decision about a host and
 * therefore PHP's rather than Fluid's.
 *
 * WHAT IT RECOGNISES
 *
 * YouTube and Vimeo, in the URL forms a person actually copies out of a
 * browser - the watch page, the short link, the share link, the embed link the
 * provider itself hands out:
 *
 *   https://www.youtube.com/watch?v=<id>       https://vimeo.com/<id>
 *   https://youtu.be/<id>                      https://vimeo.com/<id>/<hash>
 *   https://www.youtube.com/shorts/<id>        https://vimeo.com/channels/<name>/<id>
 *   https://www.youtube.com/live/<id>          https://vimeo.com/groups/<name>/videos/<id>
 *   https://www.youtube.com/embed/<id>         https://player.vimeo.com/video/<id>
 *   https://www.youtube-nocookie.com/embed/<id>
 *
 * and rewrites both onto the host that sets no cookie until the video is
 * actually played: "youtube-nocookie.com" for the one, "player.vimeo.com" with
 * "dnt=1" - Vimeo's own do-not-track parameter - for the other. That rewrite is
 * the reason this runs at all: an editor pasting the ordinary watch URL should
 * not have to know that it is the tracking one.
 *
 * It refuses what it cannot resolve to a **video** id, rather than guessing:
 * "vimeo.com/ondemand/<film>/<id>" and "youtube.com/embed/videoseries?list=…"
 * both carry a number or a word in the place an id sits, and both produce a
 * player with nothing in it. A link the reader can follow is the honest answer
 * to a URL this does not understand.
 *
 * One case is refused that it could in principle resolve: a Vimeo id of fewer
 * than six digits. The floor is what tells an id from a path segment that
 * merely looks like one - "vimeo.com/2024/01" would otherwise read as the
 * video 2024 with the unlisted hash 01 - and Vimeo has issued ids well past
 * seven digits for many years, so the ids this turns away are old enough to be
 * rare. They fall back to the link, which is the same answer any other
 * unrecognised address gets.
 *
 * WHAT IT DOES WITH ANYTHING ELSE
 *
 * **Nothing, deliberately** - and "nothing" includes the URL itself. Any other
 * host - a self-hosted player, Dailymotion, a Twitch clip, a page that merely
 * links to a video - leaves `provider` and `embedUrl` empty, and the template
 * then renders the placeholder without a button, keeping only the link to the
 * source, which is what it renders for a visitor without JavaScript.
 *
 * A URL whose scheme is **not** "http" or "https" is refused completely: `url`
 * comes back empty as well, so the template renders no element at all. That is
 * not a detail. The template puts `url` into an `href`, and a `javascript:` or
 * `data:` value there is a script an editor can run in every reader's browser -
 * escaping the attribute does not help, because the value is the payload
 * rather than the syntax. The field is a plain input with no scheme
 * constraint, so this method is the only place that check exists.
 *
 * Embedding an arbitrary URL in an iframe is the alternative, and it is
 * rejected on two grounds rather than one:
 *
 *  - It is a security decision taken on an editor's behalf. An iframe of an
 *    unknown origin runs that origin's script in the page; the value comes
 *    from a field, and a field is not a trust boundary.
 *  - It would not work often enough to be worth it. A "watch page" URL of an
 *    unknown provider is a page, not a player, and most sites refuse to be
 *    framed at all ("X-Frame-Options", "frame-ancestors") - the reader would
 *    get an empty box rather than a video, and nothing on the page could tell
 *    them why.
 *
 * A site package that has a fourth provider adds it by overriding this class:
 * the list below is the whole of the knowledge.
 */
#[Autoconfigure(tags: [['name' => 'data.processor', 'identifier' => 'externalMedia']])]
final class ExternalMediaProcessor implements DataProcessorInterface
{
    /**
     * The hosts of each provider, without a leading "www.".
     *
     * @var array<string, list<string>>
     */
    private const HOSTS = [
        'youtube' => ['youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'youtu.be'],
        'vimeo' => ['vimeo.com', 'player.vimeo.com'],
    ];

    /**
     * A YouTube video id. Eleven characters today, but the length has changed
     * before and is not part of any promise the provider makes, so the pattern
     * only states the alphabet and a sane range.
     */
    private const YOUTUBE_ID = '[A-Za-z0-9_-]{6,20}';

    /**
     * Not an id, although it sits where one does: the placeholder YouTube uses
     * for a playlist embed, "…/embed/videoseries?list=…". Framed, it is a
     * player with no video in it.
     */
    private const YOUTUBE_NOT_AN_ID = ['videoseries'];

    /**
     * @param ContentObjectRenderer $cObj The data of the content element or page
     * @param array<string, mixed> $contentObjectConfiguration The configuration of Content Object
     * @param array<string, mixed> $processorConfiguration The configuration of this processor
     * @param array<string, mixed> $processedData Key/value store of processed data (e.g. to be passed to a Fluid View)
     * @return array<string, mixed> the processed data as key/value store
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        if (isset($processorConfiguration['if.']) && !$cObj->checkIf($processorConfiguration['if.'])) {
            return $processedData;
        }

        $fieldName = (string)$cObj->stdWrapValue('fieldName', $processorConfiguration, 'tx_theme_embed_url');
        $targetVariableName = (string)$cObj->stdWrapValue('as', $processorConfiguration, 'embed');

        $processedData[$targetVariableName] = self::resolve((string)($cObj->data[$fieldName] ?? ''));

        return $processedData;
    }

    /**
     * @return array{url: string, provider: string, embedUrl: string}
     */
    public static function resolve(string $url): array
    {
        // Refused completely: not even the URL travels. The template renders
        // the link to the source out of "url", so a value that must not reach
        // an "href" must not reach this array either - see the class docblock.
        $refused = ['url' => '', 'provider' => '', 'embedUrl' => ''];

        $url = trim($url);
        $parts = $url === '' ? false : parse_url($url);
        if (!is_array($parts)) {
            return $refused;
        }

        // The scheme is checked **first**, before anything is handed on. Only
        // the two a browser follows for a document are accepted.
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if ($scheme !== 'http' && $scheme !== 'https') {
            return $refused;
        }

        // From here the URL is one a browser follows, so it may be handed on
        // as a link even where no embed comes of it.
        $linkOnly = ['url' => $url, 'provider' => '', 'embedUrl' => ''];

        $host = strtolower((string)($parts['host'] ?? ''));
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
        $path = (string)($parts['path'] ?? '');
        parse_str((string)($parts['query'] ?? ''), $query);

        if (in_array($host, self::HOSTS['youtube'], true)) {
            $id = self::youTubeId($host, $path, $query);

            return $id === '' ? $linkOnly : [
                'url' => $url,
                'provider' => 'youtube',
                'embedUrl' => 'https://www.youtube-nocookie.com/embed/' . $id,
            ];
        }

        if (in_array($host, self::HOSTS['vimeo'], true)) {
            [$id, $hash] = self::vimeoId($path);

            return $id === '' ? $linkOnly : [
                'url' => $url,
                'provider' => 'vimeo',
                // "dnt=1" is Vimeo's own do-not-track parameter, and the
                // unlisted hash has to travel as "h" once the path form has
                // been rewritten into the player form.
                'embedUrl' => 'https://player.vimeo.com/video/' . $id
                    . ($hash === '' ? '' : '?h=' . $hash)
                    . ($hash === '' ? '?' : '&') . 'dnt=1',
            ];
        }

        return $linkOnly;
    }

    /**
     * @param array<array-key, mixed> $query As `parse_str()` writes it.
     */
    private static function youTubeId(string $host, string $path, array $query): string
    {
        if ($host === 'youtu.be') {
            // The short link carries the id as the whole path.
            $id = self::matchId('#^/(' . self::YOUTUBE_ID . ')$#', $path);
        } elseif ($path === '/watch') {
            // The watch page carries it in "v", and may carry a playlist and a
            // start time beside it, which are dropped: the element embeds a
            // video, not a queue.
            $candidate = (string)($query['v'] ?? '');
            $id = preg_match('#^' . self::YOUTUBE_ID . '$#', $candidate) === 1 ? $candidate : '';
        } else {
            $id = self::matchId('#^/(?:embed|shorts|live|v)/(' . self::YOUTUBE_ID . ')$#', $path);
        }

        return in_array($id, self::YOUTUBE_NOT_AN_ID, true) ? '' : $id;
    }

    /**
     * @return array{0: string, 1: string} The video id and the unlisted hash, each possibly empty.
     */
    private static function vimeoId(string $path): array
    {
        // The path shapes Vimeo uses for a single video, written out rather
        // than reduced to "a number somewhere in the path". That shortcut took
        // the id out of "/ondemand/<film>/<id>" - a film page, not a video -
        // and read "/2024/01" as a video and an unlisted hash. Both framed a
        // player with nothing in it, where refusing gives the reader the link.
        foreach ([
            // "/76979871" and the unlisted "/76979871/a1b2c3d4e5".
            '#^/(\d{6,})(?:/([A-Za-z0-9]{6,}))?/?$#',
            // The player form, which is also what this method's caller builds.
            '#^/video/(\d{6,})/?$#',
            '#^/channels/[A-Za-z0-9_-]+/(\d{6,})/?$#',
            '#^/groups/[A-Za-z0-9_-]+/videos/(\d{6,})/?$#',
        ] as $shape) {
            if (preg_match($shape, $path, $matches) === 1) {
                return [$matches[1], $matches[2] ?? ''];
            }
        }

        return ['', ''];
    }

    private static function matchId(string $pattern, string $path): string
    {
        return preg_match($pattern, $path, $matches) === 1 ? $matches[1] : '';
    }
}
