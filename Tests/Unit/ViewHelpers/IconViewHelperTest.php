<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit\ViewHelpers;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Icon\IconSet;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Renders the ViewHelper the way the templates use it: through the parser of
 * standalone "typo3fluid/fluid", with the namespace declared in the template.
 *
 * That is deliberately not TYPO3's Fluid. The styleguide partials are also
 * rendered without TYPO3 ("Build/Scripts/renderStyleguideFixtures.php"), and
 * this is the smaller of the two environments - a ViewHelper that needs TYPO3
 * fails here first. The TYPO3 side is covered by the functional tests that
 * render the settings panel and a notice.
 */
final class IconViewHelperTest extends UnitTestCase
{
    private function render(string $source): string
    {
        $view = new TemplateView();
        $view->getRenderingContext()->getTemplatePaths()->setTemplateSource(
            '<html xmlns:theme="http://typo3.org/ns/SBUERK/ThemeExtensionDevelopment/ViewHelpers" data-namespace-typo3-fluid="true">'
            . $source
            . '</html>',
        );

        return (string)$view->render();
    }

    #[Test]
    public function anIconWithoutALabelIsDecorationHiddenFromAssistiveTechnology(): void
    {
        $markup = (new IconSet())->markup('circle-info');

        $this->assertSame(
            '<svg class="theme-icon" aria-hidden="true" focusable="false"' . substr($markup, 4),
            $this->render('<theme:icon name="circle-info" />'),
        );
    }

    #[Test]
    public function anIconWithALabelIsAnImageNamedByIt(): void
    {
        $html = $this->render('<theme:icon name="gear" label="Settings, &quot;all&quot; &amp; more" />');

        $this->assertStringStartsWith(
            '<svg class="theme-icon" role="img" aria-label="Settings, &amp;quot;all&amp;quot; &amp;amp; more" focusable="false" ',
            $html,
        );
        $this->assertStringNotContainsString('aria-hidden', $html);
    }

    #[Test]
    public function anEmptyLabelIsNoLabel(): void
    {
        $html = $this->render('<theme:icon name="gear" label="{label}" />');

        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringNotContainsString('role="img"', $html);
    }

    #[Test]
    public function theLabelAndTheClassesAreEscaped(): void
    {
        $view = new TemplateView();
        $view->getRenderingContext()->getTemplatePaths()->setTemplateSource(
            '<html xmlns:theme="http://typo3.org/ns/SBUERK/ThemeExtensionDevelopment/ViewHelpers" data-namespace-typo3-fluid="true">'
            . '<theme:icon name="gear" label="{label}" class="{class}" /></html>',
        );
        $view->assignMultiple(['label' => '"><script>', 'class' => 'a" onload="b']);
        $html = (string)$view->render();

        $this->assertStringContainsString('aria-label="&quot;&gt;&lt;script&gt;"', $html);
        $this->assertStringContainsString('class="theme-icon a&quot; onload=&quot;b"', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    #[Test]
    public function classesAreAddedToTheIconClass(): void
    {
        $this->assertStringStartsWith(
            '<svg class="theme-icon theme-settings__icon" aria-hidden="true" focusable="false" ',
            $this->render('<theme:icon name="gear" class=" theme-settings__icon " />'),
        );
    }

    #[Test]
    public function anIconThatIsNotInTheSetThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1789218002);

        $this->render('<theme:icon name="no-such-icon" />');
    }

    #[Test]
    public function aMalformedNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1789218001);

        $this->render('<theme:icon name="../LICENSE" />');
    }

    /**
     * A name an editor picked that a later version of the set no longer has
     * costs the icon, not the page.
     */
    #[Test]
    public function anOptionalIconThatIsNotInTheSetRendersNothing(): void
    {
        $this->assertSame('', $this->render('<theme:icon name="no-such-icon" optional="1" />'));
    }

    #[Test]
    public function anOptionalIconWithoutANameRendersNothing(): void
    {
        $this->assertSame('', $this->render('<theme:icon name="{name}" optional="1" />'));
    }

    #[Test]
    public function anOptionalIconThatIsInTheSetRendersAsAnyOther(): void
    {
        $this->assertSame(
            $this->render('<theme:icon name="gear" />'),
            $this->render('<theme:icon name="gear" optional="1" />'),
        );
    }

    /**
     * A record can hold anything a database column can - an import, a script,
     * a value from before the field was a picker. Under "optional" a malformed
     * name costs the icon like an unknown one; it is refused before it becomes
     * part of a path either way.
     */
    #[Test]
    public function anOptionalIconWithAMalformedNameRendersNothing(): void
    {
        $this->assertSame('', $this->render('<theme:icon name="../LICENSE" optional="1" />'));
    }

    #[Test]
    public function aBrandLogoIsRenderedFromTheBrandsSet(): void
    {
        $markup = IconSet::brands()->markup('mastodon');

        $this->assertSame(
            '<svg class="theme-icon" aria-hidden="true" focusable="false"' . substr($markup, 4),
            $this->render('<theme:icon set="brands" name="mastodon" />'),
        );
    }

    /**
     * The two sets are separate directories, and a name is looked up in the
     * one the tag asked for - not in both. A solid name asked for as a brand
     * is missing, exactly like a name that does not exist at all.
     */
    #[Test]
    public function aSolidNameIsNotFoundInTheBrandsSet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1789218002);

        $this->render('<theme:icon set="brands" name="gear" />');
    }

    /**
     * A misspelled set is a mistake in the template, not a request for the
     * default: falling back to "solid" would report the platform logo as an
     * icon that does not exist and point at the wrong thing.
     */
    #[Test]
    public function aSetTheExtensionDoesNotShipThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1789218004);

        $this->render('<theme:icon set="brand" name="mastodon" />');
    }
}
