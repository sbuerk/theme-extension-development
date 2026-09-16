<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit\DataProcessing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\DataProcessing\ExternalMediaProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The URL an editor pasted, and the embed address it becomes.
 *
 * Every case here is a URL a person can actually end up with by copying one
 * out of a browser or out of a provider's own share dialog. The two that carry
 * the most weight are the negative ones: a host nobody vetted and a scheme that
 * is not "http" must produce no embed address at all, because the value of that
 * field reaches an iframe source.
 */
final class ExternalMediaProcessorTest extends UnitTestCase
{
    private ExternalMediaProcessor $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new ExternalMediaProcessor();
    }

    /**
     * See `TableProcessorTest` for why the content object renderer is built
     * without its constructor: `process()` reads `$cObj->data` and calls
     * `stdWrapValue()`, and touches none of the services v14 wires into that
     * constructor.
     */
    private function newContentObjectRenderer(): ContentObjectRenderer
    {
        return (new \ReflectionClass(ContentObjectRenderer::class))->newInstanceWithoutConstructor();
    }

    /**
     * @return \Generator<string, array{url: string, provider: string, embedUrl: string}>
     */
    public static function recognisedUrls(): \Generator
    {
        yield 'the YouTube watch page' => [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'provider' => 'youtube',
            'embedUrl' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        ];
        yield 'a YouTube watch page without the subdomain' => [
            'url' => 'https://youtube.com/watch?v=dQw4w9WgXcQ',
            'provider' => 'youtube',
            'embedUrl' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        ];
        // A playlist and a start time are dropped: the element embeds a video.
        yield 'a YouTube watch page in a playlist' => [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=PL1234567890&t=42',
            'provider' => 'youtube',
            'embedUrl' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        ];
        yield 'the YouTube short link' => [
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
            'provider' => 'youtube',
            'embedUrl' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        ];
        yield 'a YouTube short' => [
            'url' => 'https://www.youtube.com/shorts/dQw4w9WgXcQ',
            'provider' => 'youtube',
            'embedUrl' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        ];
        yield 'a YouTube live stream' => [
            'url' => 'https://www.youtube.com/live/dQw4w9WgXcQ',
            'provider' => 'youtube',
            'embedUrl' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        ];
        // The embed link the provider's own share dialog hands out, on both
        // hosts: it is already an embed address and stays one.
        yield 'the YouTube embed link' => [
            'url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'provider' => 'youtube',
            'embedUrl' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        ];
        yield 'the YouTube embed link on the cookieless host' => [
            'url' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            'provider' => 'youtube',
            'embedUrl' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        ];
        yield 'a Vimeo video' => [
            'url' => 'https://vimeo.com/76979871',
            'provider' => 'vimeo',
            'embedUrl' => 'https://player.vimeo.com/video/76979871?dnt=1',
        ];
        yield 'an unlisted Vimeo video and its hash' => [
            'url' => 'https://vimeo.com/76979871/a1b2c3d4e5',
            'provider' => 'vimeo',
            'embedUrl' => 'https://player.vimeo.com/video/76979871?h=a1b2c3d4e5&dnt=1',
        ];
        yield 'a Vimeo video in a channel' => [
            'url' => 'https://vimeo.com/channels/staffpicks/76979871',
            'provider' => 'vimeo',
            'embedUrl' => 'https://player.vimeo.com/video/76979871?dnt=1',
        ];
        yield 'a Vimeo video in a group' => [
            'url' => 'https://vimeo.com/groups/motion/videos/76979871',
            'provider' => 'vimeo',
            'embedUrl' => 'https://player.vimeo.com/video/76979871?dnt=1',
        ];
        yield 'the Vimeo player link' => [
            'url' => 'https://player.vimeo.com/video/76979871',
            'provider' => 'vimeo',
            'embedUrl' => 'https://player.vimeo.com/video/76979871?dnt=1',
        ];
    }

    #[DataProvider('recognisedUrls')]
    #[Test]
    public function aRecognisedUrlBecomesTheAddressOfACookielessEmbed(string $url, string $provider, string $embedUrl): void
    {
        $resolved = ExternalMediaProcessor::resolve($url);

        $this->assertSame($provider, $resolved['provider']);
        $this->assertSame($embedUrl, $resolved['embedUrl']);
        $this->assertSame($url, $resolved['url'], 'The source URL is handed on unchanged, for the link to it.');
    }

    /**
     * A URL that is not embedded, but is still a URL a browser follows: the
     * template keeps it as the link to the source.
     *
     * @return \Generator<string, array{url: string}>
     */
    public static function unsupportedUrls(): \Generator
    {
        yield 'a host nobody vetted' => ['url' => 'https://videos.example.com/watch/1234'];
        yield 'another video site' => ['url' => 'https://www.dailymotion.com/video/x8abcde'];
        // The host has to *be* the provider, not merely mention it.
        yield 'a host that only looks like YouTube' => ['url' => 'https://youtube.com.example.net/watch?v=dQw4w9WgXcQ'];
        yield 'a YouTube host without a video' => ['url' => 'https://www.youtube.com/'];
        yield 'a YouTube watch page without an id' => ['url' => 'https://www.youtube.com/watch?list=PL1234567890'];
        yield 'a Vimeo host without a video' => ['url' => 'https://vimeo.com/channels/staffpicks'];
        // Refused rather than guessed at: each carries something in the place
        // an id sits, and each would frame a player with nothing in it.
        yield 'a YouTube playlist embed' => ['url' => 'https://www.youtube.com/embed/videoseries?list=PL1234567890'];
        yield 'a Vimeo on-demand film page' => ['url' => 'https://vimeo.com/ondemand/film/987654321'];
        yield 'a Vimeo path that is a date' => ['url' => 'https://vimeo.com/2024/01'];
    }

    /**
     * No embed address, so the template renders the link to the source and no
     * button - the same page a visitor without JavaScript gets.
     */
    #[DataProvider('unsupportedUrls')]
    #[Test]
    public function anUnsupportedUrlProducesNoEmbedAtAll(string $url): void
    {
        $resolved = ExternalMediaProcessor::resolve($url);

        $this->assertSame('', $resolved['provider']);
        $this->assertSame('', $resolved['embedUrl']);
        // The link survives: it is a URL a browser follows.
        $this->assertSame($url, $resolved['url']);
    }

    /**
     * @return \Generator<string, array{url: string}>
     */
    public static function refusedUrls(): \Generator
    {
        yield 'nothing at all' => ['url' => ''];
        yield 'not a URL at all' => ['url' => 'the video of the launch'];
        // The payload is the value, not the syntax: escaping the attribute
        // does not make a "javascript:" href safe, so the value must not reach
        // the template at all.
        yield 'a javascript URL' => ['url' => 'javascript:alert(document.domain)'];
        yield 'a javascript URL in capitals' => ['url' => 'JavaScript:alert(1)'];
        yield 'a data URL' => ['url' => 'data:text/html,<script>alert(1)</script>'];
        yield 'a vbscript URL' => ['url' => 'vbscript:msgbox(1)'];
        yield 'a file URL' => ['url' => 'file:///etc/passwd'];
    }

    /**
     * A URL whose scheme is not "http" or "https" is refused **completely**:
     * `url` comes back empty as well, so `ThemeExternalMedia.html` renders no
     * element at all rather than a live `href`.
     *
     * The field is a plain input with no scheme constraint, so this method is
     * the only place the check exists. Asserting `provider` and `embedUrl`
     * alone is not enough - that is exactly what this test used to do, and a
     * "javascript:" value reached the rendered link while it passed.
     */
    #[DataProvider('refusedUrls')]
    #[Test]
    public function aUrlWithASchemeABrowserWouldExecuteIsRefusedEntirely(string $url): void
    {
        $this->assertSame(
            ['url' => '', 'provider' => '', 'embedUrl' => ''],
            ExternalMediaProcessor::resolve($url),
            sprintf('"%s" must not reach the template at all.', $url),
        );
    }

    #[Test]
    public function theProcessorReadsTheFieldAndAssignsTheVariableItIsConfiguredFor(): void
    {
        $cObj = $this->newContentObjectRenderer();
        $cObj->data = ['tx_theme_embed_url' => 'https://vimeo.com/76979871'];

        $processed = $this->subject->process($cObj, [], [], []);

        $this->assertArrayHasKey('embed', $processed, 'The default target variable is "embed".');
        $this->assertSame('vimeo', $processed['embed']['provider']);
        $this->assertSame('https://player.vimeo.com/video/76979871?dnt=1', $processed['embed']['embedUrl']);
    }

    #[Test]
    public function theFieldAndTheTargetVariableAreConfigurable(): void
    {
        $cObj = $this->newContentObjectRenderer();
        $cObj->data = ['somewhere_else' => 'https://youtu.be/dQw4w9WgXcQ'];

        $processed = $this->subject->process($cObj, [], ['fieldName' => 'somewhere_else', 'as' => 'video'], []);

        $this->assertSame('youtube', $processed['video']['provider']);
    }

    /**
     * A record whose field is empty still gets the variable, so the template
     * asks about `embed.embedUrl` rather than about whether `embed` exists.
     */
    #[Test]
    public function anElementWithoutAUrlStillGetsTheVariable(): void
    {
        $processed = $this->subject->process($this->newContentObjectRenderer(), [], [], []);

        $this->assertSame(['url' => '', 'provider' => '', 'embedUrl' => ''], $processed['embed']);
    }
}
