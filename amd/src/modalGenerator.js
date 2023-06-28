import ModalFactory from "core/modal_factory";
import Url from "core/url";
import Mooin4Modal from "format_mooin4/mooin4Modal";
import { get_string as getString } from "core/str";

/**
 *
 */
export const init = () => {
  window.addEventListener('message', function(event) {
    var message = event.data;
    if (message.action === 'showModal') {
      showModal(message.modal.data);
      this.window.console.log(message.modal);
    }
  });
};

/**
 * Data from ajax request
 * @param {*} data
 */
 async function showModal(data) {
  if (data.show_chapter_modal) {
    let navdrawerChapter = document.querySelector(
      `[data-key="${data.chapter_id}"]`
    );
    navdrawerChapter.classList.add("completed");
    var footerContent = "";
    if (data.next_chapter < 0) {
       footerContent =
        '<button type="button" class="mooin4-btn mooin4-btn-special"' +
        'data-action="hide">' + await getString("close", "format_mooin4") + '<i class="bi bi-x-circle-fill"></i></button>';
    } else {
      footerContent =
        '<button type="button" class="mooin4-btn mooin4-btn-special"' +
        'data-action="hide">' + await getString("close", "format_mooin4") + '<i class="bi bi-x-circle-fill"></i></button>' +
        '<a href="' +
        Url.relativeUrl("/course/view.php", {
          id: data.course_id,
          section: data.next_chapter,
        }) +
        '" class="mooin4-btn mooin4-btn-primary">' + await getString("next_chapter", "format_mooin4") + '</a>';
    }
      const modal = await ModalFactory.create({
      title: await getString("modal_chapter_complete_title", "format_mooin4"),
      body: '<p>' + await getString("modal_chapter_complete", "format_mooin4") + '</p><i class="bi bi-check-circle"></i>',
      footer: footerContent,
      type: Mooin4Modal.TYPE,
      scrollable: false,
    });
    modal.show();
  }
  if (data.show_course_modal) {
    let navdrawerChapter = document.querySelector(
      `[data-key="${data.chapter_id}"]`
    );
    navdrawerChapter.classList.add("completed");
      const modal = await ModalFactory.create({
      title: await getString("modal_course_complete_title", "format_mooin4"),
      body: '<p>' + await getString("modal_course_complete", "format_mooin4") + '</p><i class="bi bi-mortarboard-fill"></i>',
      footer:
        '<button type="button" class="mooin4-btn mooin4-btn-primary"' +
        'data-action="hide">' + await getString("close", "format_mooin4") + '<i class="bi bi-x-circle-fill"></i></button>',
      type: Mooin4Modal.TYPE,
      scrollable: false,
    });
    modal.show();
  }
}