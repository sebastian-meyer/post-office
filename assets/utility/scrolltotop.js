/*!
 * Post Office
 * Copyright (C) 2026 Sebastian Meyer <sebastian.meyer@opencultureconsulting.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

(() => {
    'use strict'

    const isVisible = () => window.scrollY > 100
    const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches

    // Show the button only after scrolling down a bit (fading is done in CSS).
    const updateButton = () => {
        document.querySelectorAll('.scroll-to-top').forEach(button => {
            button.classList.toggle('is-visible', isVisible())
        })
    }

    window.addEventListener('scroll', updateButton, { passive: true })
    // Turbo replaces the page body, so update the (new) button after each visit.
    document.addEventListener('turbo:load', updateButton)

    // Scroll smoothly to the button's target instead of jumping there.
    document.addEventListener('click', event => {
        const button = event.target instanceof Element ? event.target.closest('a.scroll-to-top') : null
        const target = button ? document.querySelector(button.getAttribute('href')) : null

        if (target) {
            event.preventDefault()
            target.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth' })
        }
    })
})()
