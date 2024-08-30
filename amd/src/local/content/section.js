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
 * Course section format component.
 *
 * @module     core_courseformat/local/content/section
 * @class      core_courseformat/local/content/section
 * @copyright  2021 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Header from "format_moointopics/local/content/section/header";
import DndSection from "format_moointopics/local/courseeditor/dndsection";
import Templates from "core/templates";
import ModalFactory from "core/modal_factory";
import Mooin4Modal from "../../mooin4modal";
import { get_string as getString } from "core/str";
import ILD from "format_moointopics/ildhvp4";

export default class extends DndSection {
  /**
   * Constructor hook.
   */
  create() {
    // Optional component name for debugging.
    this.name = "content_section";
    // Default query selectors.
    this.selectors = {
      SECTION_ITEM: `[data-for='section_title']`,
      CM: `[data-for="cmitem"]`,
      SECTIONINFO: `[data-for="sectioninfo"]`,
      SECTIONBADGES: `[data-region="sectionbadges"]`,
      SHOWSECTION: `[data-action="sectionShow"]`,
      HIDESECTION: `[data-action="sectionHide"]`,
      SETCHAPTER: `[data-action="sectionSetChapter"]`,
      UNSETCHAPTER: `[data-action="sectionUnsetChapter"]`,
      ACTIONTEXT: `.menu-action-text`,
      ICON: `.icon`,
      H5P: `.parent-iframe`,
    };
    // Most classes will be loaded later by DndCmItem.
    this.classes = {
      LOCKED: "editinprogress",
      HASDESCRIPTION: "description",
      HIDE: "d-none",
      HIDDEN: "hidden",
      CHAPTER: "chapter",
    };

    // We need our id to watch specific events.
    this.id = this.element.dataset.id;
  }

  /**
   * Initial state ready method.
   *
   * @param {Object} state the initial state
   */
  stateReady(state) {
    this.configState(state);
    // Drag and drop is only available for components compatible course formats.
    if (this.reactive.isEditing && this.reactive.supportComponents) {
      // Section zero and other formats sections may not have a title to drag.
      const sectionItem = this.getElement(this.selectors.SECTION_ITEM);
      if (sectionItem) {
        // Init the inner dragable element.
        const headerComponent = new Header({
          ...this,
          element: sectionItem,
          fullregion: this.element,
        });
        this.configDragDrop(headerComponent);
      }
    }
    this._showLastSectionModal(state);
    this._hvpListener();
  }

  /**
   * Component watchers.
   *
   * @returns {Array} of watchers
   */
  getWatchers() {
    return [
      { watch: `section[${this.id}]:updated`, handler: this._refreshSection },
      // {watch: `section[${this.id}].sectionprogress:updated`, handler: this._updateSectionProgress}
    ];
  }

  /**
   * Validate if the drop data can be dropped over the component.
   *
   * @param {Object} dropdata the exported drop data.
   * @returns {boolean}
   */
  validateDropData(dropdata) {
    // If the format uses one section per page sections dropping in the content is ignored.
    if (dropdata?.type === "section" && this.reactive.sectionReturn != 0) {
      return false;
    }
    return super.validateDropData(dropdata);
  }

  /**
   * Get the last CM element of that section.
   *
   * @returns {element|null}
   */
  getLastCm() {
    const cms = this.getElements(this.selectors.CM);
    // DndUpload may add extra elements so :last-child selector cannot be used.
    if (!cms || cms.length === 0) {
      return null;
    }
    return cms[cms.length - 1];
  }

  /**
   * Update a content section using the state information.
   *
   * @param {object} param
   * @param {Object} param.element details the update details.
   */
  _refreshSection({ element }) {
    // Update classes.
    this.element.classList.toggle(
      this.classes.DRAGGING,
      element.dragging ?? false
    );
    this.element.classList.toggle(this.classes.LOCKED, element.locked ?? false);
    this.element.classList.toggle(
      this.classes.HIDDEN,
      !element.visible ?? false
    );
    this.element.classList.toggle(
      this.classes.CHAPTER,
      element.isChapter ?? false
    );
    this.locked = element.locked;
    // The description box classes depends on the section state.
    const sectioninfo = this.getElement(this.selectors.SECTIONINFO);
    if (sectioninfo) {
      sectioninfo.classList.toggle(
        this.classes.HASDESCRIPTION,
        element.hasrestrictions
      );
    }
    // Update section badges and menus.
    this._updateBadges(element);
    this._updateActionsMenu(element);

    if (this.reactive.isEditing) {
      //this._reloadSectionNames({ element: element });
    }
  }

  async _reloadSectionNames({ element }) {
    const title = this.getElement(this.selectors.SECTION_ITEM);
    //window.console.log(element);
    if (!element.isChapter) {
      //title.innerHTML = element.parentChapter + "." + element.innerChapterNumber + ": " + element.title;
      title.innerHTML = element.prefix;
    }
  }

  /**
   * Update a section badges using the state information.
   *
   * @param {object} section the section state.
   */
  _updateBadges(section) {
    const current = this.getElement(
      `${this.selectors.SECTIONBADGES} [data-type='iscurrent']`
    );
    current?.classList.toggle(this.classes.HIDE, !section.current);

    const hiddenFromStudents = this.getElement(
      `${this.selectors.SECTIONBADGES} [data-type='hiddenfromstudents']`
    );
    hiddenFromStudents?.classList.toggle(this.classes.HIDE, section.visible);
  }

  /**
   * Update a section action menus.
   *
   * @param {object} section the section state.
   */
  async _updateActionsMenu(section) {
    let selector;
    let newAction;
    if (section.visible) {
      selector = this.selectors.SHOWSECTION;
      newAction = "sectionHide";
    } else {
      selector = this.selectors.HIDESECTION;
      newAction = "sectionShow";
    }

    if (section.isChapter) {
      selector = this.selectors.SETCHAPTER;
      newAction = "sectionUnsetChapter";
    } else {
      selector = this.selectors.UNSETCHAPTER;
      newAction = "sectionSetChapter";
    }

    // Find the affected action.
    const affectedAction = this.getElement(selector);
    if (!affectedAction) {
      return;
    }
    // Change action.
    affectedAction.dataset.action = newAction;
    // Change text.
    const actionText = affectedAction.querySelector(this.selectors.ACTIONTEXT);
    if (affectedAction.dataset?.swapname && actionText) {
      const oldText = actionText?.innerText;
      actionText.innerText = affectedAction.dataset.swapname;
      affectedAction.dataset.swapname = oldText;
    }
    // Change icon.
    const icon = affectedAction.querySelector(this.selectors.ICON);
    if (affectedAction.dataset?.swapicon && icon) {
      const newIcon = affectedAction.dataset.swapicon;
      if (newIcon) {
        const pixHtml = await Templates.renderPix(newIcon, "core");
        Templates.replaceNode(icon, pixHtml, "");
      }
    }
  }

  async _showLastSectionModal(state) {
    const section = state.section.get(this.id);
    if (
      section.showLastSectionModal &&
      window.location.href == section.sectionurl.replace(/&amp;/g, "&")
    ) {
      const modal = await ModalFactory.create({
        type: Mooin4Modal.TYPE,
        title: await getString(
          "modal_last_section_of_chapter_title",
          "format_moointopics"
        ),
        body: Templates.render(
          "format_moointopics/local/content/modals/lastsection",
          {}
        ),
        footer: Templates.render(
          "format_moointopics/local/content/modals/modalfooterclose",
          {}
        ),
        scrollable: false,
      });
      modal.show();
      modal.showFooter();
      this.reactive.dispatch("setLastSectionModal", this.id);
    }
  }

  


  _hvpListener() {
    var parentIFrames = this.getElements(this.selectors.H5P);
    if (parentIFrames.length > 0) {
        parentIFrames.forEach(async (parentIFrame) => {
            if (parentIFrame.contentDocument) {
                var parentIFrameContent =
                    parentIFrame.contentDocument || parentIFrame.contentWindow.document;

                var nestedIFrame = parentIFrameContent.querySelector(".h5p-iframe");

                if (nestedIFrame) {
                    var H5P = nestedIFrame.contentWindow.H5P;
                    if (H5P && H5P.externalDispatcher) {
                      H5P.setFinished = function (contentId, score, maxScore, time) {
                                     //hvp Funktion hijacken, damit die Grade nicht doppelt eingetragen wird
                                    };
                                    H5P.externalDispatcher.on("xAPI", this._hvpprogress.bind(this));
                        // Warte auf das resize Event
                        await this._triggerResizeAndWait(H5P, H5P.instances[0]);

                        // Danach die Höhe anpassen
                        var nestedIFrameHeight = nestedIFrame.contentWindow.document.body.scrollHeight;
                        parentIFrame.style.height = nestedIFrameHeight + "px";
                    } else {
                        setTimeout(this._hvpListener.bind(this), 50);
                    }
                } else {
                    setTimeout(this._hvpListener.bind(this), 50);
                }
            } else {
                setTimeout(this._hvpListener.bind(this), 50);
            }
        });
    }
}

_triggerResizeAndWait(H5P, instance) {
    return new Promise((resolve) => {
        // Setze einen einmaligen Listener für das resize Event
        H5P.on(instance, 'resize', function() {
            resolve();
        });

        // Löst das resize Event aus
        H5P.trigger(instance, 'resize');
    });
}

  

  // _hvpListener() {
  //   var parentIFrames = this.getElements(this.selectors.H5P);
  //   if (parentIFrames.length > 0) {
  //     parentIFrames.forEach((parentIFrame) => {
  //       if (parentIFrame.contentDocument) {
  //         var parentIFrameContent =
  //           parentIFrame.contentDocument || parentIFrame.contentWindow.document;

  //         var nestedIFrame = parentIFrameContent.querySelector(".h5p-iframe");

  //         if (nestedIFrame) {
  //           var H5P = nestedIFrame.contentWindow.H5P;
  //           if (H5P && H5P.externalDispatcher) {
              
  //             // var nestedIFrameHeight =
  //             // nestedIFrame.contentWindow.document.body.scrollHeight;
  //             // parentIFrame.style.height = nestedIFrameHeight + "px";
  //             //ILD.init(H5P);
  //             window.console.log(H5P);
              
  //             H5P.setFinished = function (contentId, score, maxScore, time) {
  //              //hvp Funktion hijacken, damit die Grade nicht doppelt eingetragen wird
  //             };
  //             H5P.externalDispatcher.on("xAPI", this._hvpprogress.bind(this));
  //             var instance = H5P.instances[0];
  //             H5P.trigger(instance, 'resize');
  //             var nestedIFrameHeight =
  //             nestedIFrame.contentWindow.document.body.scrollHeight;
  //             parentIFrame.style.height = nestedIFrameHeight + "px";
  //           } else {
  //             setTimeout(this._hvpListener.bind(this), 50);
  //           }
  //         } else {
  //           setTimeout(this._hvpListener.bind(this), 50);
  //         }
  //       } else {
  //         setTimeout(this._hvpListener.bind(this), 50);
  //       }
  //     });
  //   }
  // }

  _hvpprogress(event) {
    window.console.log(event);
  
    if (event.getVerb() === "completed" || event.getVerb() === "answered") {
      var contentId = event.getVerifiedStatementValue([
        "object",
        "definition",
        "extensions",
        "http://h5p.org/x-api/h5p-local-content-id",
      ]);
      var score = event.getScore();
      var maxScore = event.getMaxScore();
      var statement = event.data.statement;
      var isChild = statement.context && statement.context.contextActivities &&
                statement.context.contextActivities.parent &&
                statement.context.contextActivities.parent[0] &&
                statement.context.contextActivities.parent[0].id;

      if (!isChild) {
        this.reactive.dispatch("updateSectionprogress", this.id, contentId, score, maxScore);

      }
    }
  }
}
