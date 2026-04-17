<?php
/**
 * FAQ-Stilvariante: Render-Filter, Slide-Animation und FAQPage-Strukturdaten.
 *
 * Ergänzt den gerenderten HTML-Output des core/details-Blocks mit einem
 * .gbp-faq-body-Wrapper (für die Slide-Animation) und sammelt Fragen/Antworten,
 * um sie als FAQPage JSON-LD im Frontend auszugeben.
 */
class GutenBlock_Pro_Faq_Style {

	/** @var array Gesammelte FAQ-Einträge (question + answer) dieser Seite */
	private $faq_items = array();

	/** @var bool Gibt an, ob mindestens ein FAQ-Block auf der Seite gefunden wurde */
	private $has_faq = false;

	public function init() {
		add_filter( 'render_block', array( $this, 'render' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_footer', array( $this, 'output_structured_data' ), 5 );
	}

	/**
	 * Modifiziert den gerenderten HTML-Output des FAQ-Details-Blocks:
	 *   1. Fügt .gbp-faq-body-Wrapper ein (für CSS/JS-Animation).
	 *   2. Extrahiert Frage und Antwort für die FAQPage-Strukturdaten.
	 */
	public function render( $content, $block ) {
		if ( 'core/details' !== $block['blockName'] ) {
			return $content;
		}

		$classes = isset( $block['attrs']['className'] ) ? $block['attrs']['className'] : '';
		if ( false === strpos( $classes, 'is-style-faq' ) ) {
			return $content;
		}

		// Inhalte nach </summary> in .gbp-faq-body einwickeln
		$content = preg_replace(
			'/(<\/summary>)([\s\S]*)(<\/details>)/i',
			'$1<div class="gbp-faq-body">$2</div>$3',
			$content,
			1
		);

		// Frage aus <summary> extrahieren
		$question = '';
		if ( preg_match( '/<summary[^>]*>([\s\S]*?)<\/summary>/i', $content, $q_m ) ) {
			$question = trim( wp_strip_all_tags( $q_m[1] ) );
		}

		// Antwort aus .gbp-faq-body extrahieren (HTML für Schema behalten)
		$answer = '';
		if ( preg_match( '/<div class="gbp-faq-body">([\s\S]*?)<\/div>\s*<\/details>/i', $content, $a_m ) ) {
			$answer = trim( $a_m[1] );
		}

		if ( $question && $answer ) {
			$this->faq_items[] = array(
				'question' => $question,
				'answer'   => $answer,
			);
			$this->has_faq = true;
		}

		return $content;
	}

	/**
	 * Skript und Styles nur auf Seiten einbinden, die FAQ-Blöcke enthalten.
	 */
	public function enqueue_scripts() {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post || false === strpos( $post->post_content, 'is-style-faq' ) ) {
			return;
		}

		wp_enqueue_script(
			'gutenblock-pro-faq-style',
			GUTENBLOCK_PRO_URL . 'assets/js/faq-style.js',
			array(),
			filemtime( GUTENBLOCK_PRO_PATH . 'assets/js/faq-style.js' ),
			true
		);
	}

	/**
	 * Gibt das FAQPage JSON-LD im Footer aus.
	 */
	public function output_structured_data() {
		if ( empty( $this->faq_items ) ) {
			return;
		}

		$entities = array();
		foreach ( $this->faq_items as $item ) {
			$entities[] = array(
				'@type'          => 'Question',
				'name'           => $item['question'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags( $item['answer'] ),
				),
			);
		}

		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		);

		echo '<script type="application/ld+json">'
			. wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT )
			. '</script>' . "\n";
	}
}
