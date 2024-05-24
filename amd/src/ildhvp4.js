define([
  "jquery",
  "core/ajax",
  "core/notification",
  "core/str",
  "core/url",
  "core/modal_factory",
], function (
  $,
  ajax,
  notification,
  Str,
  Url,
  ModalFactory,
) {
  /** @namespace */
  var ILD = ILD || {};

  /**
   * Interactions counter
   * @type {Array}
   */
  ILD.interactions = [];

  /**
   * SingelChoiceInteractions counter
   * @type {Array}
   */
  ILD.singleChoiceInteractions = [];

  /**
   * SubContentIds - avoid duplicated answered statement
   * @type {Array}
   */
  ILD.subIds = [];

  /**
   * Stores QuestionSet PassPercentage
   * @type {Array}
   */
  ILD.questionSetPassPercentage = [];

  /**
   * Stores Essay PassPercentage
   * @type {Array}
   */
  ILD.EssayPassPercentage = [];

  /**
   * Stores Branching scenario info
   * @type {Array}
   */
  ILD.BranchingScenario = [];

  /**
   * Stores maxScore of interactions
   * @type {Int}
   */
  ILD.maxScore = 0;

  /**
   * Stores score of interactions
   * @type {Float}
   */
  ILD.score = 0;

  /**
   * Stores percentage of interactions progress
   * @type {Float}
   */
  ILD.percentage = 0;

  /**
   * Internal H5P function listening for xAPI answered events and stores scores.
   *
   * @param {H5P.XAPIEvent} event
   */
  ILD.xAPIAnsweredListener = function (event) {
    var contentId = event.getVerifiedStatementValue([
      "object",
      "definition",
      "extensions",
      "http://h5p.org/x-api/h5p-local-content-id",
    ]);
    var isInteraction = false;

    if (event.data.statement.object.objectType == "Activity") {
      isInteraction = true;
    }

    if (
      isInteraction &&
      event.getVerb() === "answered" &&
      typeof ILD.questionSetPassPercentage[contentId] === "undefined" &&
      typeof ILD.singleChoiceInteractions[contentId] === "undefined" &&
      typeof ILD.EssayPassPercentage[contentId] === "undefined" &&
      typeof ILD.BranchingScenario[contentId] === "undefined"
    ) {
      var score = event.getScore();
      var maxScore = event.getMaxScore();
      var subContentId = event.data.statement.object.id;
      subContentId = subContentId.split("subContentId=");
      subContentId = subContentId[1];

      if (ILD.subIds.indexOf(subContentId) != -1) {
        if (typeof ILD.interactions[contentId] === "undefined") {
          ILD.interactions[contentId] = 1;
        }

        // ILD.score += score;
        // ILD.maxScore += maxScore;
        var interactions = ILD.interactions[contentId];

        ILD.percentage =
          ILD.percentage + (score / maxScore / interactions) * 100;

        ILD.setResult(contentId, ILD.percentage, 100);
      } else if (
        ILD.subIds.indexOf(subContentId) == -1 &&
        ILD.subIds.length == 0
      ) {
        var percentage = (score / maxScore) * 100;

        ILD.setResult(contentId, percentage, 100);
      }
    }

    // Check if QuestionSet is completed and percentage is set.
    if (
      typeof ILD.questionSetPassPercentage[contentId] !== "undefined" &&
      event.getVerb() === "completed"
    ) {
      var score = event.getScore();
      var maxScore = event.getMaxScore();
      var percentage = (score / maxScore) * 100;
      var passPercentage = ILD.questionSetPassPercentage[contentId];

      if (percentage >= passPercentage) {
        ILD.setResult(contentId, 100, 100);
      } else {
        ILD.setResult(contentId, percentage, 100);
      }
    }

    // Check if Essay is scored.
    if (
      typeof ILD.EssayPassPercentage[contentId] !== "undefined" &&
      event.getVerb() === "scored"
    ) {
      var score = event.getScore();
      var maxScore = event.getMaxScore();
      var percentage = (score / maxScore) * 100;

      ILD.setResult(contentId, percentage, 100);
    }

    // Check if SingelChoiceSet is completed.
    if (
      typeof ILD.singleChoiceInteractions[contentId] !== "undefined" &&
      event.getVerb() === "completed"
    ) {
      var score = event.getScore();
      var maxScore = event.getMaxScore();
      var percentage = (score / maxScore) * 100;

      ILD.setResult(contentId, percentage, 100);
    }

    // Check if BranchingScenario is completed.
    if (
      typeof ILD.BranchingScenario[contentId] !== "undefined" &&
      event.getVerb() === "completed"
    ) {
      ILD.setResult(contentId, 100, 100);
    }
  };

  /**
   * Post answered results for user and set progress.
   *
   * @param {number} contentId
   *   Identifies the content
   * @param {number} score
   *   Achieved score/points
   * @param {number} maxScore
   *   The maximum score/points that can be achieved
   */
  ILD.setResult = function (contentid, score, maxScore) {
    //window.console.log("set result");
    var promises = ajax.call([
      {
        methodname: "format_moointopics_setgrade",
        args: { contentid: contentid, score: score, maxscore: maxScore },
      },
    ]);

    promises[0]
      .done(function (data) {
        var div_id = String("mooin4ection" + data.sectionid); // Oc-progress
        var text_div_id = String("mooin4ection-text-" + data.sectionid); // Oc-progress-text-

        var percentage = Math.round(data.percentage);
        var percentage_int = String(percentage + "%");
        var percentage_text = String(percentage + "%" + " ");

        $("#" + div_id, window.parent.document).css("width", percentage_int);
        $("#" + text_div_id, window.parent.document).html(percentage_text);

        if (data.percentage === 100) {
          // var navdrawerSection = window.parent.document.querySelector(
          //   `[data-key="${data.sectionid}"]`
          // );
          //navdrawerSection.classList.add("completed");
          var promises = ajax.call([
            {
              methodname: "format_moointopics_check_completion_status",
              args: {
                section_id: Number(data.sectionid),
                isActivity: true,
                course_already_completed: data.course_already_completed,
                chapter_already_completed: data.chapter_already_completed
              },
            },
          ]);
          promises[0]
            .done(function (data) {
              var message = {
                action: "showModal",
                modal: { data },
              };
              window.top.postMessage(message, "*");
            })
            .fail();
        }
      })
      .fail(function () {window.console.log("FAILED")});
  };

  /**
   * Count interactions layers from interactive video element.
   *
   * @param contentId
   * @param content
   */
  ILD.getVideoInteractions = function (contentId, content) {
    var interactions = content.interactiveVideo.assets.interactions;
    var summaries = content.interactiveVideo.summary.task.params.summaries;
    var notAllowedInteractions = [
      "H5P.Text",
      "H5P.Table",
      "H5P.Link",
      "H5P.Image",
      "H5P.GoToQuestion",
      "H5P.Nil",
      "H5P.IVHotspot",
    ];
    var interactionsCounter = 0;
    var summariesCounter = 0;

    if (typeof interactions === "object") {
      $.each(interactions, function (i) {
        var library = interactions[i].action.library;
        var subid = interactions[i].action.subContentId;
        var foundItem = false;

        $.each(notAllowedInteractions, function (j) {
          if (library.indexOf(notAllowedInteractions[j]) > -1) {
            foundItem = true;
          }
        });

        if (!foundItem) {
          interactionsCounter++;
          ILD.subIds.push(subid);
        }
      });

      ILD.interactions[contentId] = interactionsCounter;
    }

    if (
      typeof interactions === "undefined" ||
      (typeof interactions === "object" && interactionsCounter == 0)
    ) {
      $(".h5p-iframe")[0].contentWindow.onload = function () {
        $(".h5p-iframe")[0].contentWindow.H5P.instances[0].video.on(
          "stateChange",
          function (event) {
            if (event.data === 0) {
              ILD.setResult(contentId, 100, 100);
            }
          }
        );
      };
    }

    if (summaries.length) {
      var summary = false;

      $.each(summaries, function (s) {
        if (typeof summaries[s].summary !== "undefined") {
          var subId = content.interactiveVideo.summary.task.subContentId;

          ILD.subIds.push(subId);
          summary = true;
        }
      });

      if (summary) {
        ILD.interactions[contentId] = interactionsCounter + 1;
      }
    }
  };

  /**
   * Count interactions layers from SingleChoice element.
   *
   * @param contentId
   * @param content
   */
  ILD.getSingleChoiceInteractions = function (contentId, content) {
    var interactions = content.choices;

    $.each(interactions, function (s) {
      var subid = interactions[s].subContentId;
      ILD.subIds.push(subid);
    });

    ILD.singleChoiceInteractions[contentId] = interactions.length;
  };

  /**
   *
   * @param contentId
   * @param content
   */
  ILD.getQuestionSetPercentage = function (contentId, content) {
    ILD.questionSetPassPercentage[contentId] = content.passPercentage;
  };

  /**
   *
   * @param contentId
   * @param content
   */
  ILD.getEssayPercentage = function (contentId, content) {
    ILD.EssayPassPercentage[contentId] = content.behaviour.percentagePassing;
  };

  /**
   * Check if library is InteractiveVideo or QuestionSet.
   */
  ILD.checkLibrary = function () {
    var contentId = $(".h5p-iframe.h5p-initialized").data("content-id");

    if (typeof contentId !== "undefined") {
      var contentData = H5PIntegration.contents["cid-" + contentId];
      var content = JSON.parse(contentData.jsonContent);
      var library = contentData.library;

      if (library.indexOf("H5P.InteractiveVideo") > -1) {
        ILD.getVideoInteractions(contentId, content);
      } else if (library.indexOf("H5P.QuestionSet") > -1) {
        ILD.getQuestionSetPercentage(contentId, content);
      } else if (library.indexOf("H5P.SingleChoiceSet") > -1) {
        ILD.getSingleChoiceInteractions(contentId, content);
      } else if (library.indexOf("H5P.Essay") > -1) {
        ILD.getEssayPercentage(contentId, content);
      } else if (library.indexOf("H5P.BranchingScenario") > -1) {
        ILD.BranchingScenario[contentId] = 1;
      }
    }
  };

  return {
    init: function () {
      //window.console.log("HVP triggered");
      ILD.checkLibrary();
      H5P.externalDispatcher.on("xAPI", ILD.xAPIAnsweredListener);
    },
  };
});
