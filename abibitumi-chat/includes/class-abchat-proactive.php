<?php
/**
 * Proactive page rules - the self-hosted replacement for Tidio Flows.
 *
 * A rule pairs a page match (URL patterns plus audience) with a trigger
 * (page open, dwell, scroll depth, exit intent) and a proactive message with
 * quick-reply buttons. Matching happens on the server so copy for pages a
 * visitor never opens is never shipped to the browser, and so audience,
 * office hours and cool-downs stay authoritative in PHP.
 *
 * Quick replies are injected into the chatbot flow list through the
 * `abchat_bot_flows` filter, so a click is answered by ABChat_Chatbot with no
 * extra plumbing.
 *
 * @package AbibitumiChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABChat_Proactive {

	/**
	 * Option key holding served/engaged counters per rule.
	 */
	const STATS_OPTION = 'abchat_proactive_stats';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'abchat_bot_flows', array( __CLASS__, 'inject_quick_reply_flows' ) );
	}

	/**
	 * Supported trigger types.
	 *
	 * @return array
	 */
	public static function triggers() {
		return array( 'page_open', 'dwell', 'scroll', 'exit_intent' );
	}

	/**
	 * Supported audience filters.
	 *
	 * @return array
	 */
	public static function audiences() {
		return array( 'all', 'new', 'returning', 'logged_in', 'logged_out' );
	}

	/**
	 * Supported frequency caps.
	 *
	 * @return array
	 */
	public static function frequencies() {
		return array( 'always', 'once_per_page', 'once_per_session', 'once_per_day', 'once_ever' );
	}

	/**
	 * Starter rule set. Written for the Abibitumi page structure (product
	 * listings, cart/checkout, course access, first-time visitors) and safe to
	 * edit or replace from Chat -> Settings.
	 *
	 * @return array
	 */
	public static function default_rules() {
		return array(
			array(
				'id'            => 'product-purchase-help',
				'enabled'       => 1,
				'name'          => __( 'Product page - purchase help', 'abibitumi-chat' ),
				'match'         => array( '*/product/*', '*/shop/*' ),
				'exclude'       => array( '*/cart*', '*/checkout*' ),
				'audience'      => 'all',
				'trigger'       => 'dwell',
				'delay'         => 25,
				'scroll'        => 50,
				'frequency'     => 'once_per_day',
				'priority'      => 20,
				'department'    => 'general',
				'office_hours_only' => 0,
				'message'       => __( 'Thinking about this one? I can walk you through signing up, or answer a question first.', 'abibitumi-chat' ),
				'quick_replies' => array(
					array(
						'id'     => 'pr_how_to_buy',
						'label'  => __( 'How do I sign up?', 'abibitumi-chat' ),
						'answer' => __( 'Three steps: tap Add to cart on this page, open your cart, then Proceed to checkout. Your receipt and access details are emailed as soon as payment clears. If a step will not complete, say so here and a team member picks it up.', 'abibitumi-chat' ),
					),
					array(
						'id'     => 'pr_format',
						'label'  => __( 'Is it live or recorded?', 'abibitumi-chat' ),
						'answer' => __( 'Every listing states whether it meets live on Zoom, in person, or is self-paced, along with the dates. If that is not clear on this page, ask here and we will confirm the format and schedule.', 'abibitumi-chat' ),
					),
					array(
						'id'      => 'pr_payment_trouble',
						'label'   => __( 'Payment or coupon trouble', 'abibitumi-chat' ),
						'handoff' => 1,
					),
				),
			),
			array(
				'id'            => 'checkout-rescue',
				'enabled'       => 1,
				'name'          => __( 'Cart and checkout rescue', 'abibitumi-chat' ),
				'match'         => array( '*/cart*', '*/checkout*' ),
				'exclude'       => array( '*order-received*', '*thank-you*' ),
				'audience'      => 'all',
				'trigger'       => 'exit_intent',
				'delay'         => 45,
				'scroll'        => 50,
				'frequency'     => 'once_per_session',
				'priority'      => 40,
				'department'    => 'support',
				'office_hours_only' => 0,
				'message'       => __( 'Before you go - is something blocking your checkout? I can get a person on it.', 'abibitumi-chat' ),
				'quick_replies' => array(
					array(
						'id'      => 'pr_payment_failed',
						'label'   => __( 'My payment will not go through', 'abibitumi-chat' ),
						'handoff' => 1,
					),
					array(
						'id'      => 'pr_question_first',
						'label'   => __( 'I have a question first', 'abibitumi-chat' ),
						'handoff' => 1,
					),
					array(
						'id'     => 'pr_just_looking',
						'label'  => __( 'Just looking, thanks', 'abibitumi-chat' ),
						'answer' => __( 'No problem at all. I will be right here in the corner if you need anything.', 'abibitumi-chat' ),
					),
				),
			),
			array(
				'id'            => 'course-access-help',
				'enabled'       => 1,
				'name'          => __( 'Course access help', 'abibitumi-chat' ),
				'match'         => array( '*course-access*', '*/courses/*', '*/lesson*', '*/quiz*' ),
				'exclude'       => array(),
				'audience'      => 'all',
				'trigger'       => 'dwell',
				'delay'         => 20,
				'scroll'        => 50,
				'frequency'     => 'once_per_day',
				'priority'      => 30,
				'department'    => 'support',
				'office_hours_only' => 0,
				'message'       => __( 'Need a hand getting into your class?', 'abibitumi-chat' ),
				'quick_replies' => array(
					array(
						'id'     => 'pr_cannot_access',
						'label'  => __( 'I cannot access my course', 'abibitumi-chat' ),
						'answer' => __( 'Two quick checks: make sure you are logged in with the same email you paid with, then reload this page. If it still will not open, tell me and support will unlock it for you.', 'abibitumi-chat' ),
					),
					array(
						'id'     => 'pr_zoom_link',
						'label'  => __( 'Where is my Zoom link?', 'abibitumi-chat' ),
						'answer' => __( 'Session links are emailed before each meeting and posted in the class group here on the site. If you cannot find yours, say so and we will resend it.', 'abibitumi-chat' ),
					),
					array(
						'id'      => 'pr_course_human',
						'label'   => __( 'Talk to a person', 'abibitumi-chat' ),
						'handoff' => 1,
					),
				),
			),
			array(
				'id'            => 'first-visit-orientation',
				'enabled'       => 1,
				'name'          => __( 'First visit orientation', 'abibitumi-chat' ),
				'match'         => array(),
				'exclude'       => array( '*/cart*', '*/checkout*', '*/wp-login*' ),
				'audience'      => 'new',
				'trigger'       => 'dwell',
				'delay'         => 15,
				'scroll'        => 50,
				'frequency'     => 'once_ever',
				'priority'      => 5,
				'department'    => 'general',
				'office_hours_only' => 0,
				'message'       => __( 'Akwaaba! First time here? I can point you to classes, membership, or the shop.', 'abibitumi-chat' ),
				'quick_replies' => array(
					array(
						'id'    => 'courses',
						'label' => __( 'Show me classes', 'abibitumi-chat' ),
					),
					array(
						'id'    => 'pricing',
						'label' => __( 'Membership options', 'abibitumi-chat' ),
					),
					array(
						'id'      => 'pr_orientation_human',
						'label'   => __( 'Talk to a person', 'abibitumi-chat' ),
						'handoff' => 1,
					),
				),
			),
		);
	}

	/**
	 * Configured rules, normalised and filterable.
	 *
	 * @return array
	 */
	public static function rules() {
		$rules = (array) ABChat_Settings::get( 'proactive_rules', array() );
		$out   = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$out[] = self::normalize_rule( $rule );
		}

		/**
		 * Filter the proactive rule set before matching.
		 *
		 * @param array $out Normalised rules.
		 */
		return apply_filters( 'abchat_proactive_rules', $out );
	}

	/**
	 * Pick the highest-priority rule that applies to a page and visitor.
	 *
	 * @param string $url     Page URL the visitor is on.
	 * @param array  $context returning|logged_in|shown_ids.
	 * @return array|null
	 */
	public static function match_rule( $url, $context = array() ) {
		if ( ! ABChat_Settings::get( 'proactive_enabled' ) ) {
			return null;
		}

		$context = wp_parse_args(
			$context,
			array(
				'returning' => false,
				'logged_in' => false,
				'shown_ids' => array(),
			)
		);

		$best = null;
		foreach ( self::rules() as $rule ) {
			if ( empty( $rule['enabled'] ) || '' === $rule['id'] ) {
				continue;
			}
			if ( in_array( $rule['id'], (array) $context['shown_ids'], true ) ) {
				continue;
			}
			if ( ! self::url_matches( $url, $rule['match'], $rule['exclude'] ) ) {
				continue;
			}
			if ( ! self::audience_matches( $rule['audience'], $context ) ) {
				continue;
			}
			if ( ! empty( $rule['office_hours_only'] ) && ! ABChat_Settings::is_within_office_hours() ) {
				continue;
			}
			if ( '' === trim( (string) $rule['message'] ) ) {
				continue;
			}
			if ( null === $best || $rule['priority'] > $best['priority'] ) {
				$best = $rule;
			}
		}

		/**
		 * Filter the rule chosen for this page view.
		 *
		 * @param array|null $best    Chosen rule.
		 * @param string     $url     Page URL.
		 * @param array      $context Visitor context.
		 */
		return apply_filters( 'abchat_proactive_match', $best, $url, $context );
	}

	/**
	 * Does a URL satisfy the include/exclude patterns?
	 *
	 * Patterns are matched against host + path + query, case-insensitively.
	 * A pattern containing * is a glob anchored to the whole string; a pattern
	 * without * is a plain substring test. An empty include list means the
	 * whole site.
	 *
	 * @param string $url     URL to test.
	 * @param array  $include Include patterns.
	 * @param array  $exclude Exclude patterns.
	 * @return bool
	 */
	public static function url_matches( $url, $include, $exclude = array() ) {
		$url = self::normalize_url( $url );
		if ( '' === $url ) {
			return false;
		}

		foreach ( (array) $exclude as $pattern ) {
			if ( self::pattern_hit( $pattern, $url ) ) {
				return false;
			}
		}

		$include = array_filter( array_map( 'strval', (array) $include ), 'strlen' );
		if ( empty( $include ) ) {
			return true;
		}

		foreach ( $include as $pattern ) {
			if ( self::pattern_hit( $pattern, $url ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Strip scheme and lower-case a URL for matching.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	protected static function normalize_url( $url ) {
		$url = strtolower( trim( (string) $url ) );
		$url = preg_replace( '#^[a-z][a-z0-9+.-]*://#', '', $url );
		return (string) $url;
	}

	/**
	 * Test one pattern against an already normalised URL.
	 *
	 * @param string $pattern Pattern.
	 * @param string $url     Normalised URL.
	 * @return bool
	 */
	protected static function pattern_hit( $pattern, $url ) {
		$pattern = self::normalize_url( $pattern );
		if ( '' === $pattern ) {
			return false;
		}
		if ( false === strpos( $pattern, '*' ) ) {
			return false !== strpos( $url, $pattern );
		}
		$regex = '#^' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . '$#';
		return 1 === preg_match( $regex, $url );
	}

	/**
	 * Audience gate.
	 *
	 * @param string $audience Audience key.
	 * @param array  $context  Visitor context.
	 * @return bool
	 */
	protected static function audience_matches( $audience, $context ) {
		switch ( $audience ) {
			case 'new':
				return empty( $context['returning'] );
			case 'returning':
				return ! empty( $context['returning'] );
			case 'logged_in':
				return ! empty( $context['logged_in'] );
			case 'logged_out':
				return empty( $context['logged_in'] );
		}
		return true;
	}

	/**
	 * Fill in defaults and clamp values for a single rule.
	 *
	 * @param array $rule Raw rule.
	 * @return array
	 */
	public static function normalize_rule( $rule ) {
		$rule = wp_parse_args(
			(array) $rule,
			array(
				'id'                => '',
				'enabled'           => 1,
				'name'              => '',
				'match'             => array(),
				'exclude'           => array(),
				'audience'          => 'all',
				'trigger'           => 'dwell',
				'delay'             => 20,
				'scroll'            => 50,
				'frequency'         => 'once_per_day',
				'priority'          => 10,
				'department'        => 'general',
				'office_hours_only' => 0,
				'message'           => '',
				'quick_replies'     => array(),
			)
		);

		$rule['id']       = sanitize_key( $rule['id'] );
		$rule['enabled']  = empty( $rule['enabled'] ) ? 0 : 1;
		$rule['name']     = sanitize_text_field( $rule['name'] );
		$rule['match']    = self::clean_patterns( $rule['match'] );
		$rule['exclude']  = self::clean_patterns( $rule['exclude'] );
		$rule['audience'] = in_array( $rule['audience'], self::audiences(), true ) ? $rule['audience'] : 'all';
		$rule['trigger']  = in_array( $rule['trigger'], self::triggers(), true ) ? $rule['trigger'] : 'dwell';
		$rule['delay']    = min( 600, max( 0, (int) $rule['delay'] ) );
		$rule['scroll']   = min( 100, max( 1, (int) $rule['scroll'] ) );
		$rule['priority'] = (int) $rule['priority'];
		$rule['message']  = sanitize_textarea_field( $rule['message'] );
		$rule['department'] = sanitize_key( $rule['department'] ? $rule['department'] : 'general' );
		$rule['office_hours_only'] = empty( $rule['office_hours_only'] ) ? 0 : 1;
		$rule['frequency'] = in_array( $rule['frequency'], self::frequencies(), true ) ? $rule['frequency'] : 'once_per_day';

		if ( '' === $rule['name'] ) {
			$rule['name'] = $rule['id'];
		}

		$replies = array();
		foreach ( (array) $rule['quick_replies'] as $reply ) {
			if ( ! is_array( $reply ) ) {
				continue;
			}
			$label = isset( $reply['label'] ) ? sanitize_text_field( $reply['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}
			$id = sanitize_key( isset( $reply['id'] ) && '' !== $reply['id'] ? $reply['id'] : $label );
			if ( '' === $id ) {
				continue;
			}
			$answer = isset( $reply['answer'] ) ? sanitize_textarea_field( $reply['answer'] ) : '';
			if ( ! empty( $reply['handoff'] ) ) {
				$answer = '__HANDOFF__';
			}
			$replies[] = array(
				'id'     => $id,
				'label'  => $label,
				'answer' => $answer,
			);
			if ( 4 <= count( $replies ) ) {
				break;
			}
		}
		$rule['quick_replies'] = $replies;

		return $rule;
	}

	/**
	 * Trim a list of URL patterns.
	 *
	 * @param mixed $patterns Patterns.
	 * @return array
	 */
	protected static function clean_patterns( $patterns ) {
		if ( is_string( $patterns ) ) {
			$patterns = preg_split( '/[\r\n,]+/', $patterns );
		}
		$out = array();
		foreach ( (array) $patterns as $pattern ) {
			$pattern = trim( sanitize_text_field( (string) $pattern ) );
			if ( '' !== $pattern ) {
				$out[] = $pattern;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Sanitize a whole rule set, dropping rules without an id.
	 *
	 * @param mixed $rules Array of rules or a JSON string.
	 * @return array
	 */
	public static function sanitize_rules( $rules ) {
		if ( is_string( $rules ) ) {
			$decoded = json_decode( $rules, true );
			$rules   = is_array( $decoded ) ? $decoded : array();
		}
		$out  = array();
		$seen = array();
		foreach ( (array) $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$rule = self::normalize_rule( $rule );
			if ( '' === $rule['id'] || isset( $seen[ $rule['id'] ] ) ) {
				continue;
			}
			$seen[ $rule['id'] ] = true;
			$out[]               = $rule;
		}
		return $out;
	}

	/**
	 * Front-end payload for a matched rule. Only what the widget needs.
	 *
	 * @param array $rule Rule.
	 * @return array
	 */
	public static function payload( $rule ) {
		$replies = array();
		foreach ( (array) $rule['quick_replies'] as $reply ) {
			$replies[] = array(
				'id'    => $reply['id'],
				'label' => $reply['label'],
			);
		}
		return array(
			'id'           => $rule['id'],
			'trigger'      => $rule['trigger'],
			'delay'        => (int) $rule['delay'],
			'scroll'       => (int) $rule['scroll'],
			'frequency'    => $rule['frequency'],
			'message'      => $rule['message'],
			'quickReplies' => $replies,
		);
	}

	/**
	 * Expose rule quick replies to the chatbot as flows, so a click on a
	 * proactive button is answered by the existing rule engine.
	 *
	 * @param array $flows Configured bot flows.
	 * @return array
	 */
	public static function inject_quick_reply_flows( $flows ) {
		$flows = (array) $flows;
		$ids   = array();
		foreach ( $flows as $flow ) {
			if ( isset( $flow['id'] ) ) {
				$ids[ $flow['id'] ] = true;
			}
		}

		foreach ( self::rules() as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}
			foreach ( (array) $rule['quick_replies'] as $reply ) {
				if ( '' === $reply['answer'] || isset( $ids[ $reply['id'] ] ) ) {
					continue; // No answer means it points at an existing flow.
				}
				$ids[ $reply['id'] ] = true;
				$flows[]             = array(
					'id'       => $reply['id'],
					'label'    => $reply['label'],
					'keywords' => array(),
					'answer'   => $reply['answer'],
				);
			}
		}
		return $flows;
	}

	/**
	 * Cool-down window for a frequency cap, in seconds.
	 *
	 * @param array $rule Rule.
	 * @return int
	 */
	protected static function cooldown_seconds( $rule ) {
		switch ( $rule['frequency'] ) {
			case 'always':
				return 0;
			case 'once_per_page':
				return 15 * MINUTE_IN_SECONDS;
			case 'once_per_session':
				return HOUR_IN_SECONDS;
			case 'once_ever':
				return YEAR_IN_SECONDS;
		}
		return DAY_IN_SECONDS;
	}

	/**
	 * Transient key for a visitor/rule pair.
	 *
	 * @param int    $visitor_id Visitor id.
	 * @param string $rule_id    Rule id.
	 * @return string
	 */
	protected static function cooldown_key( $visitor_id, $rule_id ) {
		return 'abchat_pr_' . (int) $visitor_id . '_' . substr( md5( (string) $rule_id ), 0, 12 );
	}

	/**
	 * Has this rule already fired for this visitor inside its window?
	 *
	 * @param int   $visitor_id Visitor id.
	 * @param array $rule       Rule.
	 * @return bool
	 */
	public static function is_cooling_down( $visitor_id, $rule ) {
		if ( self::cooldown_seconds( $rule ) <= 0 ) {
			return false;
		}
		return (bool) get_transient( self::cooldown_key( $visitor_id, $rule['id'] ) );
	}

	/**
	 * Record that a rule was served to a visitor.
	 *
	 * @param int   $visitor_id Visitor id.
	 * @param array $rule       Rule.
	 * @return void
	 */
	public static function mark_shown( $visitor_id, $rule ) {
		$seconds = self::cooldown_seconds( $rule );
		if ( $seconds > 0 ) {
			set_transient( self::cooldown_key( $visitor_id, $rule['id'] ), time(), $seconds );
		}
	}

	/**
	 * Increment a counter so the admin can see what is actually working.
	 *
	 * @param string $rule_id Rule id.
	 * @param string $kind    served|engaged.
	 * @return void
	 */
	public static function bump( $rule_id, $kind ) {
		$rule_id = sanitize_key( $rule_id );
		if ( '' === $rule_id || ! in_array( $kind, array( 'served', 'engaged' ), true ) ) {
			return;
		}
		$stats = get_option( self::STATS_OPTION, array() );
		$stats = is_array( $stats ) ? $stats : array();
		if ( ! isset( $stats[ $rule_id ] ) || ! is_array( $stats[ $rule_id ] ) ) {
			$stats[ $rule_id ] = array( 'served' => 0, 'engaged' => 0 );
		}
		$stats[ $rule_id ][ $kind ] = 1 + (int) $stats[ $rule_id ][ $kind ];
		update_option( self::STATS_OPTION, $stats, false );
	}

	/**
	 * Served/engaged counters keyed by rule id, with engagement percentages.
	 *
	 * @return array
	 */
	public static function stats() {
		$stats = get_option( self::STATS_OPTION, array() );
		$stats = is_array( $stats ) ? $stats : array();
		$out   = array();
		foreach ( self::rules() as $rule ) {
			$row = isset( $stats[ $rule['id'] ] ) ? $stats[ $rule['id'] ] : array();
			$served  = isset( $row['served'] ) ? (int) $row['served'] : 0;
			$engaged = isset( $row['engaged'] ) ? (int) $row['engaged'] : 0;
			$out[] = array(
				'id'         => $rule['id'],
				'name'       => $rule['name'],
				'enabled'    => (int) $rule['enabled'],
				'served'     => $served,
				'engaged'    => $engaged,
				'engagement' => $served > 0 ? round( ( $engaged / $served ) * 100, 1 ) : 0.0,
			);
		}
		return $out;
	}
}

