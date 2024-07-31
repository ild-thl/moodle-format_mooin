// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Course index section component.
 *
 * This component is used to control specific course section interactions like drag and drop.
 *
 * @module     core_courseformat/local/courseindex/section
 * @class      core_courseformat/local/courseindex/section
 * @copyright  2021 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import SectionTitle from 'format_moointopics/local/courseindex/sectiontitle';
import DndSection from 'format_moointopics/local/courseeditor/dndsection';
import { get_string as getString } from "core/str";

export default class Component extends DndSection {

    /**
     * Constructor hook.
     */
    create() {
        // Optional component name for debugging.
        this.name = 'courseindex_section';
        // Default query selectors.
        this.selectors = {
            SECTION_ITEM: `[data-for='section_item']`,
            SECTION_TITLE: `[data-for='section_title']`,
            CM_LAST: `[data-for="cm"]:last-child`,
            INDEXNUMBER: `[data-for='index_number']`,
            CARET: `[data-for='caret']`,
            INDEXINFOS: `[data-for='index_infos']`,
            //CHECKMARK: `[data-for='checkmark']`,
        };
        // Default classes to toggle on refresh.
        this.classes = {
            SECTIONHIDDEN: 'dimmed',
            SECTIONCURRENT: 'current',
            LOCKED: 'editinprogress',
            RESTRICTIONS: 'restrictions',
            PAGEITEM: 'pageitem',
        };

        // We need our id to watch specific events.
        this.id = this.element.dataset.id;
        this.isPageItem = false;
    }

    /**
     * Static method to create a component instance form the mustahce template.
     *
     * @param {string} target the DOM main element or its ID
     * @param {object} selectors optional css selector overrides
     * @return {Component}
     */
    static init(target, selectors) {
        return new Component({
            element: document.getElementById(target),
            selectors,
        });
    }

    /**
     * Initial state ready method.
     *
     * @param {Object} state the initial state
     */
    stateReady(state) {
        this.configState(state);
        const sectionItem = this.getElement(this.selectors.SECTION_ITEM);
        // Drag and drop is only available for components compatible course formats.
        if (this.reactive.isEditing && this.reactive.supportComponents) {
            // Init the inner dragable element passing the full section as affected region.
            const titleitem = new SectionTitle({
                ...this,
                element: sectionItem,
                fullregion: this.element,
            });
            this.configDragDrop(titleitem);
        }
        // Check if the current url is the section url.
        const section = state.section.get(this.id);
        if (window.location.href == section.sectionurl.replace(/&amp;/g, "&")) {
            //this.reactive.dispatch('setPageItem', 'section', this.id);
            //this.reactive.dispatch('setContinueSection', 'section', this.id);
            sectionItem.scrollIntoView();
        }
    }

    /**
     * Component watchers.
     *
     * @returns {Array} of watchers
     */
    getWatchers() {
        return [
            {watch: `section[${this.id}]:deleted`, handler: this.remove},
            {watch: `section[${this.id}]:updated`, handler: this._refreshSection},
            {watch: `course.pageItem:updated`, handler: this._refreshPageItem},
            {watch: `section[${this.id}].sectionprogress:updated`, handler: this._updateSectionProgress},
            //{watch: `section[${this.id}].isChapter:updated`, handler: this._reloadSectionNames},
        ];
    }

    /**
     * Get the last CM element of that section.
     *
     * @returns {element|null}
     */
    getLastCm() {
        return this.getElement(this.selectors.CM_LAST);
    }

    /**
     * Update a course index section using the state information.
     *
     * @param {Object} param details the update details.
     * @param {Object} param.element the section element
     */
    _refreshSection({state, element}) {
        // Update classes.
        const sectionItem = this.getElement(this.selectors.SECTION_ITEM);
        sectionItem.classList.toggle(this.classes.SECTIONHIDDEN, !element.visible);
        sectionItem.classList.toggle(this.classes.RESTRICTIONS, element.hasrestrictions ?? false);
        this.element.classList.toggle(this.classes.SECTIONCURRENT, element.current);
        this.element.classList.toggle(this.classes.DRAGGING, element.dragging ?? false);
        this.element.classList.toggle(this.classes.LOCKED, element.locked ?? false);
        this.locked = element.locked;
        if (this.reactive.isEditing) {
            this._reloadSectionNames({ element: element });
        }
        
    }

    async _reloadSectionNames({element}) {
        const title = this.getElement(this.selectors.SECTION_TITLE);
        //window.console.log(element);
        if (element.isChapter) {
            const caret = document.createElement("i");
            caret.classList.add("bi", "bi-caret-right-fill");
            title.innerHTML = " " + await getString("chapter", "format_moointopics") + " " + element.isChapter + ": " + element.title;
            title.prepend(caret);
        } if (!element.isChapter) {
            //title.innerHTML = element.parentChapter + "." + element.innerChapterNumber + ": " + element.title;
            title.innerHTML = element.prefix + ": " + element.title;
        }
      }

    /**
     * Handle a page item update.
     *
     * @param {Object} details the update details
     * @param {Object} details.state the state data.
     * @param {Object} details.element the course state data.
     */
    _refreshPageItem({element, state}) {
        if (!element.pageItem) {
            return;
        }
        if (element.pageItem.sectionId !== this.id && this.isPageItem) {
            this.pageItem = false;
            this.getElement(this.selectors.SECTION_ITEM).classList.remove(this.classes.PAGEITEM);
            return;
        }
        const section = state.section.get(this.id);
        if (section.indexcollapsed && !element.pageItem?.isStatic) {
            this.pageItem = (element.pageItem?.sectionId == this.id);
        } else {
            this.pageItem = (element.pageItem.type == 'section' && element.pageItem.id == this.id);
        }
        const sectionItem = this.getElement(this.selectors.SECTION_ITEM);
        sectionItem.classList.toggle(this.classes.PAGEITEM, this.pageItem ?? false);
        if (this.pageItem && !this.reactive.isEditing) {
            this.element.scrollIntoView({block: "nearest"});
        }
    }


    async _updateSectionProgress({ state, element }) {
        if (element.isCompleted) {
            const infos = this.getElement(this.selectors.INDEXINFOS);
            const checkMark = document.createElement("i");
            checkMark.classList.add("bi", "bi-check");
            infos.appendChild(checkMark);
        } else {
            //const checkmark = this.getElement(this.selectors.CHECKMARK);
            //checkmark.remove();
        }
        
        
    }
}
