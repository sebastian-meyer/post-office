/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

// Stimulus
import './stimulus_bootstrap.js';

// Bootstrap JS + CSS
import 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';

// jQuery
import $ from 'jquery';
window.$ = $;

// Utility Functions
import './utility/colormode.js';
