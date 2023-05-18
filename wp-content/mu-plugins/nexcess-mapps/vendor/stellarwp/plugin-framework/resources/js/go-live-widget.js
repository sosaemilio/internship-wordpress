/**
 * Automatic handling of the "Go Live!" widget with default settings.
 *
 * This script is responsible for finding the #stellarwp-change-domain-form element and, if it exists,
 * rendering the DomainChangeForm React element.
 */

import DomainChangeForm from './components/domain-change-form.jsx';

// Locate the root element.
const el = document.getElementById('stellarwp-change-domain-form');

if (el) {
	wp.element.render(wp.element.createElement(DomainChangeForm, el.dataset), el);
}
