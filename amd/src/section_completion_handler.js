import Ajax from "core/ajax";
import Notification from "core/notification";
import { get_string as getString } from "core/str";
import ModalFactory from "core/modal_factory";
import Mooin4Modal from "format_moointopics/mooin4Modal";
import Templates from "core/templates";
import { relativeUrl } from "core/url";

const Selectors = {
  actions: {
    buttonClicked:
      '[data-action="format_moointopics/section_completion_handler-button"]',
  },
};

export const init = async ({
  section_id,
  isLastSectionOfChapter,
  courseCompletedAlready,
}) => {
  if (isLastSectionOfChapter) {
    const modal = await ModalFactory.create({
      title: await getString(
        "modal_last_section_of_chapter_title",
        "format_moointopics"
      ),
      body:
        "<p>" +
        (await getString("modal_last_section_of_chapter", "format_moointopics")) +
        '</p><i class="bi bi-star"></i>',
      footer:
        '<button type="button" class="mooin4-btn mooin4-btn-primary"' +
        'data-action="hide">' +
        (await getString("close", "format_moointopics")) +
        '<i class="bi bi-x-circle-fill"></i></button>',
      type: Mooin4Modal.TYPE,
      scrollable: false,
    });
    modal.show();
  }

  let progressbar = document.getElementById(`mooin4ection${section_id}`);
  let percentageText = document.getElementById(
    `mooin4ection-text-${section_id}`
  );
  let navdrawerSection = document.querySelector(`[data-key="${section_id}"]`);

  document.addEventListener("click", (e) => {
    if (e.target.closest(Selectors.actions.buttonClicked)) {
      var promises = Ajax.call([
        {
          methodname: "format_moointopics_check_completion_status",
          args: {
            section_id: Number(section_id),
            isActivity: false,
            course_already_completed: courseCompletedAlready,
            chapter_already_completed: false,
          },
        },
      ]);
      promises[0]
        .done(async function (data) {
          e.target.textContent = await getString("page_read", "format_moointopics");

          e.target.classList.add("completed");
          navdrawerSection.classList.add("completed");
          e.target.disabled = true;
          progressbar.style.width = "100%";
          percentageText.innerText = "100%" + " ";

          var message = {
            action: "showModal",
            modal: { data },
          };
          window.postMessage(message, "*");

          // if (data.show_chapter_modal) {
          //   let navdrawerChapter = document.querySelector(
          //     `[data-key="${data.chapter_id}"]`
          //   );
          //   navdrawerChapter.classList.add("completed");
          //   var footerContent = "";
          //   if (data.next_chapter < 0) {
          //      footerContent =
          //       '<button type="button" class="mooin4-btn mooin4-btn-special"' +
          //        'data-action="hide">Schließen<i class="bi bi-x-circle-fill"></i></button>';
          //   } else {
          //      footerContent =
          //       '<button type="button" class="mooin4-btn mooin4-btn-special"' +
          //        'data-action="hide">Schließen<i class="bi bi-x-circle-fill"></i></button>' +
          //       '<a href="' +
          //       relativeUrl("/course/view.php", {
          //         id: data.course_id,
          //         section: data.next_chapter,
          //       }) +
          //       '" class="mooin4-btn mooin4-btn-primary">Nächstes Kapitel</a>';
          //   }
          //   const modal = await ModalFactory.create({
          //     title: "Kapitel vollständig bearbeitet",
          //     body: '<p>Du hast alle Lektionen in diesem Kapitel bearbeitet!</p><i class="bi bi-check-circle"></i>',
          //     footer: footerContent,
          //     type: Mooin4Modal.TYPE,
          //     scrollable: false,
          //   });
          //   modal.show();
          // }
          //window.console.log(data.show_course_modal);
          // if (data.show_course_modal) {
          //   let navdrawerChapter = document.querySelector(
          //     `[data-key="${data.chapter_id}"]`
          //   );
          //   navdrawerChapter.classList.add("completed");
          //   const modal = await ModalFactory.create({
          //     title: "Kurs vollständig bearbeitet",
          //     body: '<p>Du hast alle Lektionen in diesem Kurs bearbeitet!</p><i class="bi bi-mortarboard-fill"></i>',
          //     footer:
          //       '<button type="button" class="mooin4-btn mooin4-btn-special"'+
          //        'data-action="hide">Schließen<i class="bi bi-x-circle-fill"></i></button>',
          //     type: Mooin4Modal.TYPE,
          //     scrollable: false,
          //   });
          //   modal.show();
          // }
        })
        .fail();
    }
  });
};
