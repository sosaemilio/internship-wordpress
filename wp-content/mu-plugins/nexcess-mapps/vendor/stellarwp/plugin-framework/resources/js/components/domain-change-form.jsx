/**
 * Form for changing the site domain from within WP-Admin.
 */

import { __, sprintf } from '@wordpress/i18n';

export default class DomainChangeForm extends wp.element.Component {
	constructor(props) {
		super(props);
		this.state = {
			apiFailure: false,
			changed: false,
			dnsFailure: false,
			errorMessage: null,
			newDomain: null,
			skipDnsVerification: false,
			submitting: false,
		};

		this.handleDomainInputChanges = this.handleDomainInputChanges.bind(this);
		this.handleDnsValidationChanges = this.handleDnsValidationChanges.bind(this);
		this.submitDomainChange = this.submitDomainChange.bind(this);
	}

	/**
	 * Ensure that the new domain is non-empty and differs from the current domain.
	 *
	 * @return {boolean} Whether the new domain is valid.
	 */
	domainHasChanged() {
		return this.state.newDomain && this.props.currentDomain !== this.state.newDomain;
	}

	/**
	 * Handle changes to the "Skip DNS record validation" checkbox.
	 *
	 * @param {Object} ev
	 */
	handleDnsValidationChanges(ev) {
		this.setState({
			skipDnsVerification: ev.target.checked,
		});
	}

	/**
	 * Handle changes to input#stellarwp-new-domain.
	 *
	 * @param {Object} ev
	 */
	handleDomainInputChanges(ev) {
		this.setState({
			dnsFailure: false,
			newDomain: ev.target.value,
		});
	}

	/**
	 * Submit the domain change request.
	 *
	 * @param {Object} ev
	 */
	submitDomainChange(ev) {
		ev.preventDefault();

		// Abort if we're already processing.
		if (this.state.submitting) {
			return;
		}

		// Set the form state to prevent multiple submissions.
		this.setState({
			apiFailure: false,
			dnsFailure: false,
			errorMessage: null,
			submitting: true,
		});

		const opts = {
			method: 'POST',
			body: new URLSearchParams({
				_wpnonce: this.props.nonce,
				action: 'stellarwp-change-domain',
				domain: this.state.newDomain,
				skipDnsVerification: this.state.skipDnsVerification ? 1 : 0,
			}),
		};

		fetch(this.props.ajaxUrl, opts)
			.then((response) => response.json())
			.then((response) => {
				if (response.success) {
					return this.setState({ changed: true });
				}

				const err = response.data[0] || {};

				// DNS entries don't exist, we have a special error message for ths.
				if ('stellarwp-missing-dns' === err.code) {
					return this.setState({
						dnsFailure: true,
						submitting: false,
					});
				}

				// The stellarwp API request failed.
				if ('stellarwp-change-domain-failure' === err.code) {
					return this.setState({
						apiFailure: true,
						submitting: false,
					});
				}

				this.setState({
					errorMessage: err.message,
					submitting: false,
				});
			});
	}

	/**
	 * Render the form contents.
	 */
	render() {
		return (
			<form method="post" onSubmit={this.submitDomainChange}>
				<div className="stellarwp-inline-form">
					<fieldset>
						<label htmlFor="stellarwp-new-domain" className="screen-reader-text">
							{__('Domain', 'stellarwp-framework')}
						</label>

						<input
							name="stellarwp-new-domain"
							id="stellarwp-new-domain"
							type="text"
							className="code"
							placeholder="www.yourdomain.com"
							onKeyUp={this.handleDomainInputChanges}
							disabled={this.state.submitting}
							required
						/>
					</fieldset>

					<button
						type="submit"
						className="button button-primary"
						disabled={!this.domainHasChanged() || this.state.submitting}
					>
						{__('Connect', 'stellarwp-framework')}
					</button>

					<input name="_stellarwp_nonce" type="hidden" value={this.props.nonce} />
				</div>

				<div className="stellarwp-form-extension" hidden={!this.state.newDomain}>
					<label
						htmlFor="stellarwp-skip-dns-verification"
						className="stellarwp-inline-checkbox stellarwp-change-domain-verification-checkbox"
					>
						<input
							name="stellarwp-skip-dns-verification"
							id="stellarwp-skip-dns-verification"
							type="checkbox"
							onChange={this.handleDnsValidationChanges}
						/>
						<b>{__('Optional', 'stellarwp-framework')}:&nbsp;</b>
						{__('Skip DNS record validation', 'stellarwp-framework')}
					</label>

					<p className="description">
						{__(
							"By default, we check that the appropriate DNS records are set before changing your domain. If you're behind a proxy or are using another more advanced DNS technique, you may wish to skip this validation.",
							'stellarwp-framework',
						)}
					</p>

					{this.state.skipDnsVerification && (
						<div className="notice notice-error notice-alt inline stellarwp-change-domain">
							<p>
								{__(
									'Your site may become inaccessible if its DNS records are invalid, so please double-check your DNS configuration before clicking "Connect"',
									'stellarwp-framework',
								)}
							</p>
							<p>
								{__(
									"If anything does go wrong, please reach out to support and we'll get you up and running!",
									'stellarwp-framework',
								)}
							</p>
						</div>
					)}
				</div>

				{this.state.dnsFailure && (
					<div className="notice notice-error notice-alt inline stellarwp-change-domain">
						<p>
							{sprintf(
								/* translators: %s: domain name */
								__(
									'The DNS records for %s do not currently point to this site.',
									'stellarwp-framework',
								),
								this.state.newDomain,
							)}
						</p>
						<p>
							<a
								href={this.props.dnsHelpUrl}
								target="_blank"
								rel="noopener noreferrer"
							>
								{__(
									'Learn more about changing your DNS records',
									'stellarwp-framework',
								)}
							</a>
						</p>
					</div>
				)}

				{this.state.apiFailure && (
					<div className="notice notice-error notice-alt inline stellarwp-change-domain">
						<p>
							<strong>
								{__(
									"Something went wrong updating your site's domain name.",
									'stellarwp-framework',
								)}
							</strong>
						</p>
					</div>
				)}

				{this.state.errorMessage && (
					<div className="notice notice-error notice-alt inline stellarwp-change-domain">
						<p>
							<strong>
								{__(
									"Something went wrong updating your site's domain name.",
									'stellarwp-framework',
								)}
							</strong>
						</p>
						<p>{this.state.errorMessage}</p>
					</div>
				)}

				{this.state.changed && (
					<div className="notice notice-success notice-alt inline stellarwp-change-domain">
						<p>
							{__(
								'Your domain is being updated, and will be available shortly!',
								'stellarwp-framework',
							)}
						</p>
					</div>
				)}
			</form>
		);
	}
}
