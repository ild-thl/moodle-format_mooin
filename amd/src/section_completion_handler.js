//import {call as fetchMany} from 'core/ajax';

// export const check_completion_status = (
//   course_id,
//   section_id,
// ) => fetchMany([{
//   methodname: 'format_mooin4_check_completion_status',
//   args: {
//     course_id,
//     section_id,
//   },
// }])[0];

const Selectors = {
  actions: {
    buttonClicked:
      '[data-action="format_mooin4/section_completion_handler-button"]',
  },
};

export const init = ({section_id, course_id}) => {
  var xhr = new XMLHttpRequest();
  xhr.onreadystatechange = () => {
    if (xhr.readyState == 4) {
      if (xhr.status == 200) {
        var result = JSON.parse(xhr.response);
        if (result) {
            window.console.log(result.completed);
        }
      }
    }
  };

  let progressbar = document.getElementById(`mooin4ection${section_id}`);
  let percentageText = document.getElementById(`mooin4ection-text-${section_id}`);
  let navdrawerSection = document.querySelector(`[data-key="${section_id}"]`);

  document.addEventListener("click", (e) => {
    if (e.target.closest(Selectors.actions.buttonClicked)) {
      var formData = new FormData();
      formData.append("section", section_id);
      formData.append("course_id", course_id);
      xhr.open("POST", "format/mooin4/complete_section.php", true);

      xhr.send(formData);

      e.target.textContent = "Seite gelesen";

      e.target.classList.add('completed');
      navdrawerSection.classList.add('completed');
      e.target.disabled = true;
      progressbar.style.width = "100%";
      percentageText.innerText = "100%";
    }
  });

  // let completeBtn = document.getElementById(`btn_comp`);
  // let sectionNumber;
  // window.console.log(section);
  // completeBtn.addEventListener(() => {
  //   sectionNumber = section;
  //   window.console.log(sectionNumber);
  // });
};
