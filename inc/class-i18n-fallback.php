<?php
/**
 * I18n fallback for GutenBlock Pro.
 *
 * The plugin's msgids are written in German because the team works in German.
 * For every non-`de_*` locale we used to rely on a compiled `gutenblock-pro-en_US.mo`
 * catalog — but that catalog falls out of date every time a new string is added
 * and German texts leak into the English UI.
 *
 * This class is the safety net: it intercepts the `gettext` filter for our
 * textdomain and serves a hand-curated English translation whenever the
 * compiled .mo file does not provide one. The map covers admin pages
 * (Provisioning Wizard, Features, Sections, Prompts, License, Pattern Creator,
 * AI Generator, Contact Form settings). Strings that read identically in EN and DE (e.g. "Sections",
 * "Tokens", "Premium") simply have no entry — gettext then returns the msgid
 * unchanged.
 *
 * When a new German string is wrapped in `__()` / `esc_html__()` etc.,
 * add it to {@see self::map()} below to keep the English UI complete.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filters gettext output for our textdomain with a hard-coded German→English map.
 */
class GutenBlock_Pro_I18n_Fallback {

	/**
	 * Whether this instance is active (= non-German locale).
	 *
	 * @var bool
	 */
	private $active = false;

	/**
	 * Cached translation map (German msgid => English translation).
	 *
	 * @var array<string, string>|null
	 */
	private static $cache = null;

	/**
	 * Hook the filters when the current locale is not German.
	 */
	public function init(): void {
		// Use determine_locale() so the check matches the locale WordPress
		// actually translates with: the user's profile language in the admin,
		// the site language on the frontend. get_locale() (site only) would
		// leak German into an English admin when the user locale differs.
		$locale = function_exists( 'determine_locale' ) ? (string) determine_locale() : (string) get_locale();
		if ( $locale === '' || 0 === strpos( $locale, 'de' ) ) {
			return;
		}
		$this->active = true;

		add_filter( 'gettext_gutenblock-pro', array( $this, 'translate' ), 10, 2 );
		add_filter( 'gettext_with_context_gutenblock-pro', array( $this, 'translate_with_context' ), 10, 3 );
		add_filter( 'ngettext_gutenblock-pro', array( $this, 'translate_n' ), 10, 4 );
	}

	/**
	 * Standard gettext filter callback.
	 *
	 * @param string $translation Translation produced by WP (may equal the msgid).
	 * @param string $text        Original msgid.
	 * @return string
	 */
	public function translate( $translation, $text ) {
		if ( ! $this->active || ! is_string( $text ) ) {
			return $translation;
		}
		// If WP already returned a translation different from the msgid the
		// compiled .mo catalog is winning — keep that.
		if ( is_string( $translation ) && $translation !== $text ) {
			return $translation;
		}
		$map = self::map();
		return isset( $map[ $text ] ) ? $map[ $text ] : $translation;
	}

	/**
	 * gettext_with_context filter: WordPress passes the context separately;
	 * the map is keyed on the msgid alone since all our DE strings are unique.
	 *
	 * @param string $translation Translation produced by WP.
	 * @param string $text        Original msgid.
	 * @param string $context     Translation context (unused).
	 * @return string
	 */
	public function translate_with_context( $translation, $text, $context ) {
		unset( $context );
		return $this->translate( $translation, $text );
	}

	/**
	 * Plural gettext filter callback.
	 *
	 * @param string $translation Translation produced by WP.
	 * @param string $single      Singular msgid.
	 * @param string $plural      Plural msgid.
	 * @param int    $number      Count used for plural selection.
	 * @return string
	 */
	public function translate_n( $translation, $single, $plural, $number ) {
		if ( ! $this->active ) {
			return $translation;
		}
		$key = $number === 1 ? $single : $plural;
		return $this->translate( $translation, $key );
	}

	/**
	 * The translation map. Keep alphabetised for easier diffs.
	 *
	 * Strings that read the same in English and German (e.g. "Premium",
	 * "Section", "Tokens", "Footer", "Header") intentionally have no entry —
	 * gettext then returns the msgid unchanged.
	 *
	 * @return array<string, string>
	 */
	public static function map(): array {
		if ( self::$cache !== null ) {
			return self::$cache;
		}

		self::$cache = array(
			// Provisioning Wizard (gutenblock-provisioning) ----------------
			'Import'                                                           => 'Import',
			'Import aus GutenBlock-SaaS'                                       => 'Import from GutenBlock SaaS',
			'Übernimm deine im SaaS-Editor gestaltete Site in diese WordPress-Installation: Seiten, Header/Footer, Menü und Medien.'
				=> 'Bring the site you designed in the SaaS editor into this WordPress installation: pages, header/footer, menu and media.',
			'Status'                                                           => 'Status',
			'Letzter Import'                                                   => 'Last import',
			'Seiten zuletzt'                                                   => 'Pages (last)',
			'SaaS-Styles aktiv'                                                => 'SaaS styles active',
			'Aktiv'                                                            => 'Active',
			'Aus'                                                              => 'Off',
			'Import starten'                                                   => 'Start import',
			'Trage deinen Provisioning-Token aus dem GutenBlock-Dashboard (Site → Ausliefern) ein.'
				=> 'Enter your provisioning token from the GutenBlock dashboard (Site → Deliver).',
			'Provisioning-Token'                                               => 'Provisioning token',
			'64-stelliger Hex-Token aus dem Dashboard'                         => '64-character hex token from the dashboard',
			'Styles aus dem SaaS übernehmen'                                   => 'Apply styles from SaaS',
			'Hinweis:'                                                         => 'Note:',
			'Aktiviert ersetzt diese Option Farben, Schriften (inkl. Heading-Weight) und semantische Schriftgrößen (H1–H4, Absatz) deiner Site durch die im SaaS festgelegten Werte. Diese werden als zusätzliches Stylesheet eingehängt und überschreiben Theme-Defaults via `!important`. Bestehende Block-individuelle Overrides bleiben erhalten.'
				=> 'When enabled, this option replaces your site\'s colours, fonts (incl. heading weight) and semantic font sizes (H1–H4, paragraph) with the values defined in the SaaS. They are added as an extra stylesheet and override theme defaults via `!important`. Existing per-block overrides are kept.',
			'Startseite ersetzen'                                              => 'Replace front page',
			'Aktiviert ersetzt diese Option deine aktuelle WordPress-Startseite durch die im SaaS gestaltete Startseite. Lasse die Option deaktiviert, wenn deine bestehende Startseite erhalten bleiben soll – alle weiteren Seiten werden in beiden Fällen importiert.'
				=> 'When enabled, this option replaces your current WordPress front page with the home page designed in the SaaS. Leave it off to keep your existing front page – all other pages are imported either way.',
			'Header aus SaaS importieren'                                      => 'Import header from SaaS',
			'Aktiviert erstellt einen neuen Header-Template-Part neben den bestehenden. Vorherige Importe aus dem SaaS werden ersetzt; eigene oder andere Header bleiben erhalten.'
				=> 'When enabled, this creates a new header template part next to the existing ones. Previous SaaS imports are replaced; your own or other headers are kept.',
			'Footer aus SaaS importieren'                                      => 'Import footer from SaaS',
			'Aktiviert erstellt einen neuen Footer-Template-Part neben den bestehenden. Vorherige Importe aus dem SaaS werden ersetzt; eigene oder andere Footer bleiben erhalten.'
				=> 'When enabled, this creates a new footer template part next to the existing ones. Previous SaaS imports are replaced; your own or other footers are kept.',
			'Pattern-Bundle (intern)'                                          => 'Pattern bundle (internal)',
			'Admin'                                                            => 'Admin',
			'Statisches Pattern-Bundle für den SaaS-Canvas neu bauen.'         => 'Rebuild the static pattern bundle used by the SaaS canvas.',
			'Letzter Build'                                                    => 'Last build',
			'Patterns'                                                         => 'Patterns',
			'Pattern-Bundle neu bauen'                                         => 'Rebuild pattern bundle',
			'Pattern-Bundle neu gebaut. Patterns: %d'                          => 'Pattern bundle rebuilt. Patterns: %d',
			'Pattern-Bundle-Builder ist nicht verfügbar.'                      => 'Pattern bundle builder is not available.',
			'Du bist nicht berechtigt, das Pattern-Bundle neu zu bauen.'       => 'You are not allowed to rebuild the pattern bundle.',
			'Bitte gültigen Token einfügen.'                                   => 'Please enter a valid token.',
			'Ungültiges Manifest.'                                             => 'Invalid manifest.',
			'Manifest HTTP %1$d: %2$s'                                         => 'Manifest HTTP %1$d: %2$s',
			'Import erfolgreich. Seiten: %1$d · Styles: %2$s · Startseite: %3$s · Template-Parts: %4$s'
				=> 'Import successful. Pages: %1$d · Styles: %2$s · Front page: %3$s · Template parts: %4$s',
			'aus SaaS übernommen'                                              => 'taken from SaaS',
			'unverändert'                                                      => 'unchanged',
			'ersetzt'                                                          => 'replaced',
			'beibehalten'                                                      => 'kept',

			// Admin / Sections page (gutenblock-pro) -----------------------
			'GutenBlock Pro'                                                   => 'GutenBlock Pro',
			'Sections'                                                         => 'Sections',
			'Seiten'                                                           => 'Pages',
			'Keine Sections gefunden.'                                         => 'No sections found.',
			'CSS/JS Editor'                                                    => 'CSS/JS Editor',
			'Info'                                                             => 'Info',
			'Speichern'                                                        => 'Save',
			'Gespeichert!'                                                     => 'Saved!',
			'Fehler beim Speichern'                                            => 'Error while saving',
			'Datei wirklich zurücksetzen?'                                     => 'Really reset this file?',
			'Meta-Anpassungen aus dem Uploads-Ordner entfernen und Plugin-Stand wiederherstellen?'
				=> 'Remove the meta overrides from the uploads folder and restore the plugin version?',
			'Aktuellen Editor-Inhalt als neues Original in das Plugin übernehmen?'
				=> 'Adopt the current editor content as the new plugin original?',
			'Als Original übernehmen'                                          => 'Adopt as original',
			'Als Original übernommen!'                                         => 'Adopted as original!',
			'Auf Original zurücksetzen'                                        => 'Reset to original',
			'Angepasst'                                                        => 'Modified',
			'Löschen'                                                          => 'Delete',
			'Premium Pattern'                                                  => 'Premium pattern',
			'Premium/Free'                                                     => 'Premium/Free',
			'— Keine Gruppe —'                                                 => '— No group —',
			'Stilvarianten'                                                    => 'Style variants',
			'Block-Varianten'                                                  => 'Block variants',
			'Wähle ein Pattern oder eine Block-Variante aus der Liste.'        => 'Pick a pattern or block variant from the list.',
			'Block-Erweiterungen'                                              => 'Block extensions',
			'Übersicht aller registrierten Block-Varianten und Block-Erweiterungen von GutenBlock Pro.'
				=> 'Overview of all registered block variants and block extensions of GutenBlock Pro.',
			'Noch keine Block-Erweiterungen registriert.'                      => 'No block extensions registered yet.',
			'Block:'                                                           => 'Block:',
			'Variante:'                                                        => 'Variant:',
			'Statistiken'                                                      => 'Statistics',
			'Sections gesamt'                                                  => 'Total sections',
			'CSS gesamt'                                                       => 'Total CSS',
			'JS gesamt'                                                        => 'Total JS',
			'Plugin Version'                                                   => 'Plugin version',
			'Conditional Loading'                                              => 'Conditional loading',
			'GutenBlock Pro lädt CSS und JS nur für Sections, die auf der aktuellen Seite verwendet werden.'
				=> 'GutenBlock Pro loads CSS and JS only for sections that are used on the current page.',
			'Die Erkennung basiert auf der CSS-Klasse:'                        => 'Detection is based on the CSS class:',
			'Pfade'                                                            => 'Paths',
			'Plugin-Verzeichnis'                                               => 'Plugin directory',
			'Sections-Verzeichnis'                                             => 'Sections directory',
			'Vorschau: %s (%s)'                                                => 'Preview: %s (%s)',
			'Kein content.html — keine Vorschau.'                              => 'No content.html — no preview.',
			'Name (Titel)'                                                     => 'Name (title)',
			'Beschreibung'                                                     => 'Description',
			'Kurze, sichtbare Pattern-Beschreibung (Tooltip im Inserter).'     => 'Short, visible pattern description (tooltip in the inserter).',
			'Strukturelle Beschreibung (Layout, Hintergrundtyp, Buttons, …)'   => 'Structural description (layout, background type, buttons, …)',
			'Typ'                                                              => 'Type',
			'Section'                                                          => 'Section',
			'Seite'                                                            => 'Page',
			'Gruppe'                                                           => 'Group',
			'Keywords'                                                         => 'Keywords',
			'hero, cta, button (kommagetrennt)'                                => 'hero, cta, button (comma separated)',
			'Content-Felder'                                                   => 'Content fields',
			'Tonalitäten'                                                      => 'Tonalities',
			'Aktive Varianten werden im Inserter und in der KI-Auswahl berücksichtigt.'
				=> 'Active variants are considered in the inserter and in the AI selection.',
			'Premium'                                                          => 'Premium',
			'Paid Feature'                                                     => 'Paid feature',
			'Meta speichern'                                                   => 'Save meta',
			'Meta auf Plugin-Stand zurücksetzen'                               => 'Reset meta to plugin defaults',
			'Änderungen werden in uploads/gutenblock-pro/patterns/…/pattern.php gespeichert und überschreiben nicht die Plugin-Datei.'
				=> 'Changes are stored in uploads/gutenblock-pro/patterns/…/pattern.php and do not overwrite the plugin file.',
			'Tonalität'                                                        => 'Tone',
			'Ordnet diese Seitenvorlage einer SaaS-Unterseite zu (z. B. „Services Page“). Nur sichtbar bei Typ = Seite.'
				=> 'Maps this page template to a SaaS sub-page (e.g. "Services Page"). Only visible when type = page.',
			'Ziel-Unterseite'                                                  => 'Target sub-page',
			'— Keine Zuordnung (Standalone) —'                                 => '— No mapping (standalone) —',
			'About Page'                                                       => 'About page',
			'Services Page'                                                    => 'Services page',
			'Blog Post'                                                        => 'Blog post',
			'Legal / Impressum'                                                => 'Legal / Imprint',

			// Features page (gutenblock-pro-features) ----------------------
			'Features'                                                         => 'Features',
			'Aktiviere oder deaktiviere optionale Funktionen von GutenBlock Pro.'
				=> 'Enable or disable optional GutenBlock Pro features.',
			'Stilvarianten für Blöcke'                                         => 'Style variants for blocks',
			'Aktiviere oder deaktiviere einzelne Block-Stilvarianten. Deaktivierte Varianten werden weder registriert noch geladen.'
				=> 'Enable or disable individual block style variants. Disabled variants are neither registered nor loaded.',
			'Einstellungen speichern'                                          => 'Save settings',
			'KI-Übersetzungssprachen'                                          => 'AI translation languages',
			'Aktiviere die Sprachen, in die im Editor übersetzt werden kann. Jede aktivierte Sprache erscheint als Button in der Sidebar.'
				=> 'Enable the languages content can be translated to in the editor. Each enabled language appears as a button in the sidebar.',
			'Sprachen speichern'                                               => 'Save languages',
			// Feature definitions (dynamic __() calls in class-features-page.php)
			'Admin Bar ersetzen'                                               => 'Replace admin bar',
			'Ersetzt die WordPress Admin-Bar durch ein kleines schwebendes Icon unten rechts mit kontextabhängigen Bearbeitungslinks.'
				=> 'Replaces the WordPress admin bar with a small floating icon at the bottom right with context-aware editing links.',
			'Container-Formen'                                                 => 'Container shapes',
			'Ermöglicht Abschlussformen (z. B. Welle, Diagonale) oben oder unten an Gruppen-Blöcken als Stilvarianten.'
				=> 'Adds finishing shapes (e.g. wave, diagonal) at the top or bottom of group blocks as style variants.',
			'Material Icons Block'                                             => 'Material Icons block',
			'Custom Block für Google Material Symbols mit Inline-SVG, Suche und Größen-/Farbsteuerung.'
				=> 'Custom block for Google Material Symbols with inline SVG, search and size/colour controls.',
			'Horizontal Scroll'                                                => 'Horizontal scroll',
			'Horizontales Scrollen für Spalten-Blöcke mit Snap, Dots und Pfeilen.'
				=> 'Horizontal scrolling for columns blocks with snap, dots and arrows.',
			'Text-Dekorationen (Toolbar)'                                      => 'Text decorations (toolbar)',
			'Fügt Einkreisen, Freihand-Unterstreichen und Marker als Toolbar-Buttons für Überschriften und Absätze hinzu.'
				=> 'Adds circle, freehand underline and marker as toolbar buttons for headings and paragraphs.',
			// Block variant labels & descriptions (passed through __() in get_block_variant_definitions())
			'Simple'                                                           => 'Simple',
			'Transparenter Button ohne Hintergrund – nur Text mit Pfeil-Icon und Hover-Animation'
				=> 'Transparent button without background – just text with an arrow icon and hover animation',
			'Arrow Circle'                                                     => 'Arrow Circle',
			'Pill-Button mit animiertem Kreis-Pfeil-Icon rechts'               => 'Pill button with an animated circle arrow icon on the right',
			'Checkmark'                                                        => 'Checkmark',
			'Zeigt Checkmarks (✓) statt Bullets für alle Listenelemente'       => 'Shows check marks (✓) instead of bullets for all list items',
			'Space Between'                                                    => 'Space Between',
			'Verteilt Kinder-Elemente gleichmäßig (justify-content: space-between)'
				=> 'Distributes child elements evenly (justify-content: space-between)',
			'Step Circle'                                                      => 'Step Circle',
			'Zeigt nummerierte Schritt-Kreise in einer Gruppe'                 => 'Shows numbered step circles in a group',
			'Vertical Center'                                                  => 'Vertical Center',
			'Zentriert Kinder-Elemente vertikal (align-items: center)'         => 'Centres child elements vertically (align-items: center)',
			// Additional descriptions registered by class-block-registry.php
			'Pill-förmiger Button mit eingebettetem Kreis-Pfeil rechts und Hover-Animation'
				=> 'Pill-shaped button with an embedded circle arrow on the right and hover animation',
			'Vertikale Verteilung: Inhalte füllen die volle Höhe mit gleichmäßigem Abstand'
				=> 'Vertical distribution: content fills the full height with even spacing',
			'Zeigt den Absatz als nummerierte Kreisfläche (z.B. für Schritte)' => 'Shows the paragraph as a numbered circle (e.g. for steps)',
			'Zentriert den Inhalt vertikal per Flexbox'                        => 'Centres the content vertically using flexbox',

			// AI settings / Prompts page (gutenblock-pro-ai) ---------------
			'Prompts'                                                          => 'Prompts',
			'GutenBlock Pro - Prompts'                                         => 'GutenBlock Pro – Prompts',
			'Kontext'                                                          => 'Context',
			'Beschreibe wer du bist und was du machst. Diese Information ist die Grundlage für alle KI-generierten Texte.'
				=> 'Describe who you are and what you do. This information is the basis for all AI-generated text.',
			'z.B. Ich bin ein Marketing-Berater und helfe Unternehmen bei der digitalen Transformation...'
				=> 'e.g. I am a marketing consultant helping companies with their digital transformation…',
			'Optional: Feintuning'                                             => 'Optional: fine-tuning',
			'Diese Einstellungen sind optional und dienen der Verfeinerung. Der Kontext oben reicht für den Start.'
				=> 'These settings are optional and provide further refinement. The context above is enough to get started.',
			'Stil'                                                             => 'Style',
			'Definiere den Schreibstil und technische Vorgaben für die Texte.' => 'Define the writing style and technical guidance for the text.',
			'Block-Prompts'                                                    => 'Block prompts',
			'Optional: Diese Prompts werden mit der API von gutenblock.com synchronisiert und definieren, welcher Text für welches Block-Element generiert wird. Custom Prompts überschreiben die API-Prompts.'
				=> 'Optional: these prompts are synchronised with the gutenblock.com API and define which text is generated for which block element. Custom prompts override the API prompts.',
			'Prompts mit API synchronisieren'                                  => 'Sync prompts with API',
			'Lade Prompts...'                                                  => 'Loading prompts…',
			'Keine Prompts geladen. Klicke auf "Aktualisieren" um die Prompts von gutenblock.com zu laden.'
				=> 'No prompts loaded. Click "Refresh" to load prompts from gutenblock.com.',
			'Keine Prompts gefunden. Prüfe die Verbindung zu gutenblock.com'   => 'No prompts found. Check the connection to gutenblock.com.',
			'Block-ID'                                                         => 'Block ID',
			'Prompt (API)'                                                     => 'Prompt (API)',
			'Optional: Eigener Prompt (überschreibt API-Prompt)'               => 'Optional: custom prompt (overrides API prompt)',
			'Custom Prompt'                                                    => 'Custom prompt',
			'Custom Prompts speichern'                                         => 'Save custom prompts',
			'Custom Prompts erfolgreich gespeichert'                           => 'Custom prompts saved successfully',
			'Prompts erfolgreich aktualisiert'                                 => 'Prompts updated successfully',
			'Token-Verbrauch'                                                  => 'Token usage',
			'Tokens'                                                           => 'Tokens',
			'Verbleibend: %1$s Tokens. Reset: %2$s'                            => 'Remaining: %1$s tokens. Reset: %2$s',
			'Token-Limit erreicht'                                             => 'Token limit reached',
			'Monatliches Token-Limit erreicht. Upgrade auf Pro für unbegrenzte Generierung.'
				=> 'Monthly token limit reached. Upgrade to Pro for unlimited generation.',
			'Unbegrenzte Tokens (Pro)'                                         => 'Unlimited tokens (Pro)',
			'Pro-Lizenz aktiv'                                                 => 'Pro license active',
			'Kostenlose Version'                                               => 'Free version',
			'1 Mio. AI-Tokens monatlich'                                       => '1M AI tokens per month',
			'Einmalig zahlen, lebenslang nutzen'                               => 'Pay once, use forever',
			'Alle Premium-Patterns freigeschalten'                             => 'All premium patterns unlocked',
			'Jetzt kaufen'                                                     => 'Buy now',
			'Noch keine Lizenz? %s'                                            => 'No license yet? %s',

			// License page (gutenblock-pro-license) ------------------------
			'Lizenz'                                                           => 'License',
			'Licence'                                                          => 'License',
			'GutenBlock Pro - Lizenz'                                          => 'GutenBlock Pro – License',
			'GutenBlock Pro - Licence'                                         => 'GutenBlock Pro – License',
			'Lizenz aktivieren'                                                => 'Activate license',
			'Lizenz deaktivieren'                                              => 'Deactivate license',
			'Lizenz erfolgreich aktiviert!'                                    => 'License activated successfully!',
			'Lizenz deaktiviert'                                               => 'License deactivated',
			'Bitte Lizenzschlüssel eingeben'                                   => 'Please enter a license key',
			'Aktivierung fehlgeschlagen'                                       => 'Activation failed',
			'Aktiviere...'                                                     => 'Activating…',
			'Deaktiviere...'                                                   => 'Deactivating…',

			// Pattern Creator modal & errors -------------------------------
			'GutenBlock Pro Pattern erstellen'                                 => 'Create GutenBlock Pro pattern',
			'Pattern Name'                                                     => 'Pattern name',
			'Mein neues Pattern'                                               => 'My new pattern',
			'Slug'                                                             => 'Slug',
			'Wird automatisch generiert'                                       => 'Generated automatically',
			'Kurze Beschreibung des Patterns'                                  => 'Short description of the pattern',
			'Beschreibung & AI Hint mit KI generieren (EN)'                    => 'Generate description & AI hint with AI (EN)',
			'AI Hint'                                                          => 'AI hint',
			'Strukturelle Analyse (Layout, Hintergrundtyp, CTA-Variante, Medien)'
				=> 'Structural analysis (layout, background type, CTA variant, media)',
			'Tonalitäts-Varianten anbieten (Dark + Soft)'                      => 'Offer tonality variants (dark + soft)',
			'Erzeugt Dark- und Soft-Varianten dieses Patterns für FSE und SaaS.'
				=> 'Creates dark and soft variants of this pattern for FSE and SaaS.',
			'Pattern erstellen'                                                => 'Create pattern',
			'Erstelle Pattern...'                                              => 'Creating pattern…',
			'Pattern erfolgreich erstellt!'                                    => 'Pattern created successfully!',
			'Bestehendes Pattern aktualisieren'                                => 'Update existing pattern',
			'Content aktualisieren'                                            => 'Update content',
			'Nur content.html wird überschrieben. CSS und JS bleiben erhalten.' => 'Only content.html is overwritten. CSS and JS are kept.',
			'Pattern existiert bereits. Content wird aktualisiert.'            => 'Pattern already exists. Content will be updated.',
			'Als GB Pro Pattern speichern'                                     => 'Save as GB Pro pattern',
			'Bitte gib einen Namen ein.'                                       => 'Please enter a name.',
			'Bitte zuerst Blöcke auswählen.'                                   => 'Please select blocks first.',
			'Bitte wähle mindestens einen Block aus.'                          => 'Please select at least one block.',
			'Nicht möglich: Top-Block hat Bild/Gradient als Hintergrund.'      => 'Not possible: top block has an image/gradient background.',
			'KI generiert…'                                                    => 'Generating with AI…',
			'KI-Vorschlag fehlgeschlagen.'                                     => 'AI suggestion failed.',
			'Abbrechen'                                                        => 'Cancel',
			'Weiter'                                                           => 'Continue',
			'Zurück'                                                           => 'Back',
			'Fehler beim Erstellen des Patterns'                               => 'Error while creating the pattern',
			'Wird benötigt'                                                    => 'Required',

			// Container forms / shapes -------------------------------------
			'Welle'                                                            => 'Wave',
			'Diagonale'                                                        => 'Diagonal',
			'Bogen'                                                            => 'Arc',
			'Spitze'                                                           => 'Spike',
			'Zickzack'                                                         => 'Zigzag',
			'Layered Wave'                                                     => 'Layered Wave',
			'Asymmetric'                                                       => 'Asymmetric',
			'Ausgefüllt'                                                       => 'Filled',

			// AI generator errors ------------------------------------------
			'API-Key nicht konfiguriert'                                       => 'API key not configured',
			'AI-Antwort konnte nicht geparst werden.'                          => 'AI response could not be parsed.',
			'AI-Antwort war leer.'                                             => 'AI response was empty.',
			'AI-Generierung fehlgeschlagen'                                    => 'AI generation failed',
			'Fehler bei der AI-Generierung: '                                  => 'AI generation error: ',
			'Keine Berechtigung'                                               => 'Not authorised',
			'Keine Content-Felder angegeben'                                   => 'No content fields specified',
			'Kein Text zum Übersetzen'                                         => 'No text to translate',
			'Prompt ist erforderlich'                                          => 'A prompt is required',
			'Rate-Limit erreicht. Bitte warte einen Moment.'                   => 'Rate limit reached. Please wait a moment.',
			'Schreibe einen passenden Text für das Element „%s".'              => 'Write fitting text for the element "%s".',
			'Seite %d'                                                         => 'Page %d',
			'Header'                                                           => 'Header',
			'Footer'                                                           => 'Footer',
			'Meta'                                                             => 'Meta',
			'–'                                                                => '–',

			// Contact form settings (gutenblock-pro-contact-form) ----------
			'Kontaktformular'                                                  => 'Contact Form',
			'Keine Berechtigung.'                                              => 'Not authorised.',
			'Test-E-Mail an %s gesendet.'                                      => 'Test email sent to %s.',
			'•••••••• (gespeichert – leer lassen zum Behalten)'                => '•••••••• (saved – leave blank to keep)',
			'Empfänger'                                                        => 'Recipient',
			'Empfänger-E-Mail'                                                 => 'Recipient email',
			'Eingehende Anfragen werden an diese Adresse gesendet. Standard: Website-Admin-E-Mail.'
				=> 'Incoming requests are sent to this address. Default: site admin email.',
			'Betreff'                                                          => 'Subject',
			'Platzhalter {site_name} wird durch den Website-Namen ersetzt. Leer = Standard.'
				=> 'Placeholder {site_name} is replaced with the site name. Empty = default.',
			'E-Mail-Versand einrichten'                                        => 'Set up email delivery',
			'Damit Kontaktformular-Anfragen zuverlässig zugestellt werden, sollte deine Website E-Mails nicht direkt über WordPress versenden. Verbinde stattdessen ein echtes E-Mail-Postfach oder einen Versanddienst wie Brevo.'
				=> 'For reliable contact form delivery, your site should not send email directly through WordPress. Connect a real mailbox or a sending service such as Brevo instead.',
			'Empfohlen: Brevo'                                                 => 'Recommended: Brevo',
			'Kostenloser Versanddienst – am einfachsten für die zuverlässige Zustellung.'
				=> 'Free sending service – the easiest way to deliver reliably.',
			'Vorhandenes E-Mail-Postfach'                                      => 'Existing email mailbox',
			'Nutze dein bestehendes Postfach (z. B. IONOS, Strato, Google).'
				=> 'Use your existing mailbox (e.g. IONOS, Strato, Google).',
			'Erweitert: Manuell'                                               => 'Advanced: Manual',
			'Für Fortgeschrittene: SMTP-Daten selbst eintragen.'
				=> 'For advanced users: enter SMTP details yourself.',
			'Kostenloses Konto bei Brevo erstellen und Absender bestätigen.'
				=> 'Create a free Brevo account and confirm your sender.',
			'Unter "SMTP & API" einen %s erzeugen (nicht den API-Key).'
				=> 'Under "SMTP & API", create a %s (not the API key).',
			'SMTP-Schlüssel'                                                   => 'SMTP key',
			'SMTP-Login und SMTP-Schlüssel hier einfügen.'
				=> 'Paste SMTP login and SMTP key here.',
			'SMTP-Login (Benutzername)'                                        => 'SMTP login (username)',
			'Absender-E-Mail'                                                  => 'Sender email',
			'Die in Brevo bestätigte Absender-Adresse.'
				=> 'The sender address confirmed in Brevo.',
			'Absender-Name'                                                    => 'Sender name',
			'optional'                                                         => 'optional',
			'Wird automatisch gesetzt: %s'                                     => 'Set automatically: %s',
			'Wird automatisch gesetzt: '                                       => 'Set automatically: ',
			'Anbieter'                                                         => 'Provider',
			'E-Mail-Adresse'                                                   => 'Email address',
			'Passwort'                                                         => 'Password',
			'Das Passwort deines E-Mail-Postfachs.'
				=> 'Your email mailbox password.',
			'SMTP-Host'                                                        => 'SMTP host',
			'Port'                                                             => 'Port',
			'587 (TLS), 465 (SSL) oder 25 (unverschlüsselt).'
				=> '587 (TLS), 465 (SSL) or 25 (unencrypted).',
			'Verschlüsselung'                                                  => 'Encryption',
			'Keine'                                                            => 'None',
			'Benutzername'                                                     => 'Username',
			'Leer lassen, um das gespeicherte Passwort beizubehalten.'
				=> 'Leave blank to keep the saved password.',
			'Absender-Adresse'                                                 => 'Sender address',
			'Test-E-Mail'                                                      => 'Test email',
			'Sendet eine Test-E-Mail an den oben gespeicherten Empfänger. Bitte zuerst speichern.'
				=> 'Sends a test email to the recipient saved above. Please save first.',
			'Test-E-Mail senden'                                               => 'Send test email',
			'Sende…'                                                           => 'Sending…',
			'Fehler beim Senden.'                                              => 'Error while sending.',
			'Benutzername ist die vollständige E-Mail-Adresse. Passwort = dein E-Mail-Postfach-Passwort.'
				=> 'Username is your full email address. Password = your mailbox password.',
			'Wichtig: Hier ist ein App-Passwort nötig, nicht dein normales Google-Passwort. Erstelle es in deinem Google-Konto unter "Sicherheit → App-Passwörter".'
				=> 'Important: an app password is required here, not your regular Google password. Create one in your Google account under Security → App passwords.',
			'Benutzername ist die vollständige E-Mail-Adresse. Eventuell muss SMTP-AUTH im Microsoft-Admincenter aktiviert sein.'
				=> 'Username is your full email address. SMTP AUTH may need to be enabled in the Microsoft admin centre.',
			'Anderer Anbieter'                                                 => 'Other provider',
			'Für andere Anbieter nutze bitte die erweiterten SMTP-Einstellungen weiter unten.'
				=> 'For other providers, please use the advanced SMTP settings below.',
			'keine'                                                            => 'none',
			'Die Anmeldung am Mailserver ist fehlgeschlagen. Bitte prüfe Benutzername und Passwort bzw. den SMTP-Schlüssel.'
				=> 'Login to the mail server failed. Please check username and password or the SMTP key.',
			'Die verschlüsselte Verbindung ist fehlgeschlagen. Bitte prüfe die Verschlüsselung (TLS oder SSL) und den Port.'
				=> 'The encrypted connection failed. Please check encryption (TLS or SSL) and the port.',
			'Die Verbindung zum Mailserver konnte nicht hergestellt werden. Bitte prüfe Host und Port.'
				=> 'Could not connect to the mail server. Please check host and port.',
			'Die E-Mail konnte nicht gesendet werden. Bitte prüfe die Einstellungen oder kontaktiere den Support deines Anbieters.'
				=> 'The email could not be sent. Please check the settings or contact your provider\'s support.',
			'Den Checkbox-Text direkt im Block bearbeiten. Links über die Editor-Werkzeugleiste setzen.'
				=> 'Edit the checkbox text directly in the block. Add links via the editor toolbar.',
			'Kontaktformular Block'                                            => 'Contact Form Block',
			'Schlankes, sicheres Kontaktformular als nativer Block: konfigurierbare Felder, Honeypot- und Rate-Limit-Spamschutz, E-Mail-Versand mit optionalem SMTP.'
				=> 'Lean, secure contact form as a native block: configurable fields, honeypot and rate-limit spam protection, email delivery with optional SMTP.',

			// Consent / Tracking (gutenblock-pro-consent) ------------------
			'Blende ein schlankes Consent-Banner auf der Website ein und lade Tracking-Skripte erst nach Einwilligung. Empfohlen wird der Google Tag Manager; ohne GTM kannst du einzelne IDs direkt eintragen.'
				=> 'Show a lean consent banner on the site and load tracking scripts only after consent. Google Tag Manager is recommended; without GTM you can enter individual IDs directly.',
			'Banner & Darstellung'                                             => 'Banner & display',
			'Steuere, ob und wie das Consent-Banner auf der Website erscheint.' => 'Control whether and how the consent banner appears on the site.',
			'Consent-Banner'                                                   => 'Consent banner',
			'Banner aktivieren und Tracking erst nach Einwilligung laden'      => 'Enable the banner and load tracking only after consent',
			'Hintergrund'                                                      => 'Background',
			'Seite leicht abdunkeln, bis der Nutzer eine Wahl trifft (hebt das Banner besser hervor)'
				=> 'Slightly dim the page until the visitor makes a choice (highlights the banner)',
			'Datenschutz-Link'                                                 => 'Privacy policy link',
			'URL zur Datenschutzerklärung. Wird im Banner verlinkt.'           => 'URL of your privacy policy. Linked in the banner.',
			'Banner-Titel'                                                     => 'Banner title',
			'Optional. Leer = Standardtext in der Seitensprache.'              => 'Optional. Empty = default text in the site language.',
			'Banner-Text'                                                      => 'Banner text',
			'Wir respektieren deine Privatsphäre'                              => 'We respect your privacy',
			'Wir nutzen Cookies und ähnliche Technologien für Statistik und Marketing. Du kannst selbst entscheiden, welche Kategorien du zulässt.'
				=> 'We use cookies and similar technologies for statistics and marketing. You decide which categories to allow.',
			'Empfohlen'                                                        => 'Recommended',
			'Ein Container für alle Tags – verwalte Analytics, Ads, Meta und LinkedIn direkt im GTM.'
				=> 'One container for all tags – manage Analytics, Ads, Meta and LinkedIn directly in GTM.',
			'GTM Container-ID'                                                 => 'GTM Container ID',
			'Wenn gesetzt, wird nur der Tag Manager geladen. Die direkten IDs unten werden dann ignoriert.'
				=> 'When set, only the Tag Manager is loaded. The direct IDs below are then ignored.',
			'GTM immer laden'                                                  => 'Always load GTM',
			'Tag Manager bereits vor der Einwilligung laden'                   => 'Load the Tag Manager before consent',
			'Der Tag Manager selbst setzt ohne Tags keine Cookies (cookieless). Tags im GTM respektieren weiterhin den Consent Mode und feuern erst nach Einwilligung. Nur sinnvoll, wenn eine GTM-Container-ID gesetzt ist.'
				=> 'The Tag Manager itself sets no cookies without tags (cookieless). Tags inside GTM still respect Consent Mode and only fire after consent. Only useful when a GTM container ID is set.',
			'Direkte IDs'                                                      => 'Direct IDs',
			'Ohne GTM'                                                         => 'Without GTM',
			'Nur nutzen, wenn du keinen Tag Manager verwendest.'              => 'Only use this if you do not use a Tag Manager.',
			'Statistik'                                                        => 'Statistics',
			'GA4 Measurement-ID'                                               => 'GA4 Measurement ID',
			'Wird nach Einwilligung „Statistik“ geladen.'                      => 'Loaded after "Statistics" consent.',
			'Meta Pixel-ID'                                                    => 'Meta Pixel ID',
			'Google Ads Conversion-ID'                                         => 'Google Ads Conversion ID',
			'Google Ads Conversion-Label (optional)'                           => 'Google Ads conversion label (optional)',
			'LinkedIn Partner-ID'                                              => 'LinkedIn Partner ID',
			'Wird nach Einwilligung „Marketing“ geladen.'                      => 'Loaded after "Marketing" consent.',
			'Technik'                                                          => 'Technical',
			'Erweiterte Einstellungen für die Einwilligungssteuerung.'         => 'Advanced settings for consent control.',
			'Consent-Mode-Defaults setzen (analytics_storage und ad_storage standardmäßig „denied“, bis der Nutzer einwilligt)'
				=> 'Set Consent Mode defaults (analytics_storage and ad_storage default to "denied" until the visitor consents)',
			'Seite neu laden'                                                  => 'Reload page',
			'Seite nach geänderter Einwilligung neu laden'                     => 'Reload the page after a changed consent',
			'Ohne Reload greifen entzogene Einwilligungen erst beim nächsten Seitenaufruf, da bereits geladene Skripte nicht entfernt werden können. Mit dieser Option wird die Seite direkt neu geladen, sobald der Nutzer seine Auswahl ändert.'
				=> 'Without a reload, revoked consent only takes effect on the next page view, because already-loaded scripts cannot be removed. With this option the page reloads immediately when the visitor changes their choice.',
			'Einstellungen erneut öffnen'                                      => 'Reopen settings',
			'Biete Besuchern jederzeit die Möglichkeit, ihre Einwilligung zu ändern – z. B. im Footer oder in der Datenschutzerklärung.'
				=> 'Give visitors the option to change their consent at any time – e.g. in the footer or the privacy policy.',
			'Versieh einen beliebigen Link mit der CSS-Klasse %s. Ein Klick darauf öffnet das Consent-Banner direkt in der Einstellungsansicht.'
				=> 'Add the CSS class %s to any link. Clicking it opens the consent banner straight in its settings view.',
			'Cookie-Einstellungen'                                             => 'Cookie settings',
		);

		return self::$cache;
	}
}
