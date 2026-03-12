/**
 * WooCommerce GoCardless Payments — Blocks Entry Point
 *
 * Registers all three GoCardless payment method components with the
 * WooCommerce Blocks payment method registry. Each component is self-
 * contained and receives its server-side configuration via `getSetting()`.
 *
 * Registration pattern follows the WooCommerce Blocks AbstractPaymentMethodType
 * convention: the key passed to `registerPaymentMethod` must match the PHP
 * `$name` property of the corresponding integration class.
 *
 * Dependencies (supplied by WC Blocks as wp-scripts externals):
 *   - @woocommerce/blocks-registry  → registerPaymentMethod
 *   - @woocommerce/settings         → getSetting
 *   - @wordpress/element            → createElement, useState, useEffect
 *   - @wordpress/html-entities      → decodeEntities
 *   - @wordpress/i18n               → __
 *
 * @package WC_GoCardless_Payments
 * @since   1.0.0
 */

/* global wc, React */

const { registerPaymentMethod } = wc.wcBlocksRegistry;
const { getSetting }            = wc.wcSettings;
const { createElement, useState } = wp.element;
const { decodeEntities }        = wp.htmlEntities;
const { __ }                    = wp.i18n;

// ---------------------------------------------------------------------------
// Shared UI primitives
// ---------------------------------------------------------------------------

/**
 * Render the GoCardless sandbox (test mode) badge.
 *
 * @param {Object} props
 * @param {string} props.label  Badge label text.
 * @return {React.JSX.Element|null}
 */
function SandboxBadge( { label } ) {
	return createElement(
		'span',
		{ className: 'wc-gocardless-sandbox-badge' },
		label
	);
}

/**
 * Render the redirect notice paragraph.
 *
 * @param {Object} props
 * @param {string} props.notice  Notice text.
 * @return {React.JSX.Element}
 */
function RedirectNotice( { notice } ) {
	return createElement(
		'p',
		{ className: 'wc-gocardless-redirect-notice' },
		notice
	);
}

/**
 * Render the GoCardless branding label (title + optional sandbox badge).
 *
 * @param {Object}  props
 * @param {string}  props.title      Gateway title.
 * @param {boolean} props.isSandbox  Whether in sandbox/test mode.
 * @param {string}  props.badgeLabel Sandbox badge label text.
 * @return {React.JSX.Element}
 */
function PaymentMethodLabel( { title, isSandbox, badgeLabel } ) {
	return createElement(
		'span',
		{ className: 'wc-gocardless-blocks-label' },
		decodeEntities( title ),
		isSandbox && createElement( SandboxBadge, { label: badgeLabel } )
	);
}

// ---------------------------------------------------------------------------
// Direct Debit component
// ---------------------------------------------------------------------------

/**
 * Saved Mandate Token selector.
 *
 * Renders a radio list of the customer's existing mandate tokens plus a
 * "Use new bank account" option. The selected token ID is stored in the
 * active payment data so PHP's process_payment() can read it from POST.
 *
 * @param {Object}   props
 * @param {Array}    props.tokens           Array of serialised token objects.
 * @param {string}   props.selectedToken    Currently selected token ID or 'new'.
 * @param {Function} props.onTokenChange    Callback when selection changes.
 * @param {Object}   props.i18n             Translated strings.
 * @return {React.JSX.Element}
 */
function SavedTokenSelector( { tokens, selectedToken, onTokenChange, i18n } ) {
	return createElement(
		'div',
		{ className: 'wc-gocardless-saved-tokens' },
		createElement( 'p', { className: 'wc-gocardless-saved-label' }, i18n.saved_mandate ),
		tokens.map( ( token ) =>
			createElement(
				'label',
				{
					key: token.tokenId,
					className: 'wc-gocardless-token-label',
				},
				createElement( 'input', {
					type: 'radio',
					name: 'wc-gocardless-token',
					value: token.tokenId,
					checked: selectedToken === token.tokenId,
					onChange: () => onTokenChange( token.tokenId ),
				} ),
				' ',
				decodeEntities( token.displayName )
			)
		),
		createElement(
			'label',
			{ className: 'wc-gocardless-token-label wc-gocardless-token-new' },
			createElement( 'input', {
				type: 'radio',
				name: 'wc-gocardless-token',
				value: 'new',
				checked: selectedToken === 'new',
				onChange: () => onTokenChange( 'new' ),
			} ),
			' ',
			i18n.use_new_mandate
		)
	);
}

/**
 * Build the Direct Debit checkout block content component.
 *
 * @param {Object} settings  Server-side data from get_payment_method_data().
 * @return {Function} React functional component.
 */
function buildDirectDebitContent( settings ) {
	return function DirectDebitContent( { eventRegistration, emitResponse } ) {
		const { onPaymentSetup } = eventRegistration;
		const hasSavedTokens     = settings.saved_tokens && settings.saved_tokens.length > 0;
		const defaultToken       = hasSavedTokens ? settings.saved_tokens[ 0 ].tokenId : 'new';

		const [ selectedToken, setSelectedToken ] = useState( defaultToken );

		// Register payment data before WC Blocks submits the order.
		// Passes the selected token_id so process_payment() can use a saved mandate.
		useEffect( () => {
			const unsubscribe = onPaymentSetup( () => {
				const paymentData = {};

				if ( selectedToken && selectedToken !== 'new' ) {
					paymentData[ 'wc-gocardless_direct_debit-payment-token' ] = selectedToken;
				} else {
					paymentData[ 'wc-gocardless_direct_debit-payment-token' ] = 'new';
					if ( settings.show_save_option ) {
						const saveCheckbox = document.getElementById(
							'wc-gocardless-save-mandate'
						);
						paymentData[ 'wc-gocardless_direct_debit-new-payment-method' ] =
							saveCheckbox && saveCheckbox.checked ? 'true' : 'false';
					}
				}

				return {
					type: emitResponse.responseTypes.SUCCESS,
					meta: { paymentMethodData: paymentData },
				};
			} );

			return unsubscribe;
		}, [ onPaymentSetup, selectedToken ] );

		return createElement(
			'div',
			{ className: 'wc-gocardless-payment-box wc-gocardless-dd-box' },

			// Saved token selector (only when tokens exist).
			hasSavedTokens && createElement( SavedTokenSelector, {
				tokens: settings.saved_tokens,
				selectedToken,
				onTokenChange: setSelectedToken,
				i18n: settings.i18n,
			} ),

			// Redirect notice (only when using new mandate).
			( ! hasSavedTokens || selectedToken === 'new' ) &&
				createElement( RedirectNotice, { notice: settings.i18n.redirect_notice } ),

			// Save mandate checkbox (only for new mandates, logged-in users).
			( ! hasSavedTokens || selectedToken === 'new' ) &&
				settings.show_save_option &&
				createElement(
					'label',
					{ className: 'wc-gocardless-save-label' },
					createElement( 'input', {
						type: 'checkbox',
						id: 'wc-gocardless-save-mandate',
						defaultChecked: true,
					} ),
					' ',
					settings.i18n.save_mandate
				),

			// Sandbox notice.
			settings.is_sandbox &&
				createElement(
					'p',
					{ className: 'wc-gocardless-sandbox-notice' },
					settings.i18n.sandbox_notice
				)
		);
	};
}

/**
 * Register GoCardless Direct Debit with WC Blocks.
 */
( function () {
	const settings = getSetting( 'gocardless_direct_debit_data', {} );

	if ( ! settings || ! settings.title ) {
		return;
	}

	registerPaymentMethod( {
		name: 'gocardless_direct_debit',

		label: createElement( PaymentMethodLabel, {
			title:      settings.title,
			isSandbox:  settings.is_sandbox,
			badgeLabel: settings.sandbox_label,
		} ),

		ariaLabel: settings.i18n
			? settings.i18n.aria_label
			: 'GoCardless Direct Debit',

		content: createElement( buildDirectDebitContent( settings ), null ),

		edit: createElement(
			'div',
			{ className: 'wc-gocardless-payment-box' },
			createElement( 'p', null, decodeEntities( settings.description || '' ) ),
			createElement( RedirectNotice, {
				notice: settings.i18n
					? settings.i18n.redirect_notice
					: 'You will be redirected to GoCardless.',
			} )
		),

		canMakePayment: () => true,

		supports: {
			features:       settings.supports || [ 'products' ],
			showSavedCards: true,
			showSaveOption: !! settings.show_save_option,
		},
	} );
}() );

// ---------------------------------------------------------------------------
// Instant Bank Pay component
// ---------------------------------------------------------------------------

( function () {
	const settings = getSetting( 'gocardless_instant_bank_data', {} );

	if ( ! settings || ! settings.title ) {
		return;
	}

	/**
	 * IBP checkout block content.
	 */
	function InstantBankContent() {
		return createElement(
			'div',
			{ className: 'wc-gocardless-payment-box wc-gocardless-ibp-box' },

			createElement( RedirectNotice, { notice: settings.i18n.redirect_notice } ),

			settings.show_bank_logos &&
				settings.bank_logos &&
				settings.bank_logos.length > 0 &&
				createElement(
					'div',
					{ className: 'wc-gocardless-bank-list' },
					settings.bank_logos.map( ( bank ) =>
						createElement(
							'span',
							{ key: bank, className: 'wc-gocardless-bank-badge' },
							bank
						)
					)
				),

			settings.is_sandbox &&
				createElement(
					'p',
					{ className: 'wc-gocardless-sandbox-notice' },
					settings.i18n.sandbox_notice
				)
		);
	}

	registerPaymentMethod( {
		name: 'gocardless_instant_bank',

		label: createElement( PaymentMethodLabel, {
			title:      settings.title,
			isSandbox:  settings.is_sandbox,
			badgeLabel: settings.sandbox_label,
		} ),

		ariaLabel: settings.i18n
			? settings.i18n.aria_label
			: 'GoCardless Instant Bank Pay',

		content:  createElement( InstantBankContent, null ),
		edit:     createElement( InstantBankContent, null ),

		canMakePayment: () => true,

		supports: {
			features:       settings.supports || [ 'products' ],
			showSavedCards: false,
			showSaveOption: false,
		},
	} );
}() );

// ---------------------------------------------------------------------------
// VRP component
// ---------------------------------------------------------------------------

( function () {
	const settings = getSetting( 'gocardless_vrp_data', {} );

	if ( ! settings || ! settings.title ) {
		return;
	}

	/**
	 * Format a monetary amount with the store's currency symbol.
	 *
	 * @param {number} amount          Decimal amount.
	 * @param {string} currencySymbol  Currency symbol.
	 * @return {string} Formatted string.
	 */
	function formatAmount( amount, currencySymbol ) {
		return currencySymbol + amount.toLocaleString( undefined, {
			minimumFractionDigits: 0,
			maximumFractionDigits: 2,
		} );
	}

	/**
	 * VRP checkout block content.
	 */
	function VRPContent() {
		const symbol = settings.currency_symbol || '£';

		return createElement(
			'div',
			{ className: 'wc-gocardless-payment-box wc-gocardless-vrp-box' },

			createElement( RedirectNotice, { notice: settings.i18n.redirect_notice } ),

			// Consent limits summary — regulatory transparency requirement.
			createElement(
				'p',
				{ className: 'wc-gocardless-vrp-consent-summary' },
				settings.i18n.consent_limits,
				' ',
				settings.i18n.per_payment.replace(
					'%s',
					formatAmount( settings.max_per_payment, symbol )
				),
				', ',
				settings.i18n.per_month.replace(
					'%s',
					formatAmount( settings.max_per_month, symbol )
				),
				'.'
			),

			settings.is_sandbox &&
				createElement(
					'p',
					{ className: 'wc-gocardless-sandbox-notice' },
					settings.i18n.sandbox_notice
				)
		);
	}

	registerPaymentMethod( {
		name: 'gocardless_vrp',

		label: createElement( PaymentMethodLabel, {
			title:      settings.title,
			isSandbox:  settings.is_sandbox,
			badgeLabel: settings.sandbox_label,
		} ),

		ariaLabel: settings.i18n
			? settings.i18n.aria_label
			: 'GoCardless Variable Recurring Payments',

		content:  createElement( VRPContent, null ),
		edit:     createElement( VRPContent, null ),

		canMakePayment: () => true,

		supports: {
			features:       settings.supports || [ 'products', 'subscriptions' ],
			showSavedCards: false,
			showSaveOption: false,
		},
	} );
}() );
