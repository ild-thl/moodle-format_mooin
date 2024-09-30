// Import der Abhängigkeiten
import $ from 'jquery';
import ajax from 'core/ajax';
import notification from 'core/notification';
import Str from 'core/str';
import Url from 'core/url';
import ModalFactory from 'core/modal_factory';

// ILD Namespace Definition
const ILD = {};

// Interactions counter
ILD.interactions = [];

// SingleChoiceInteractions counter
ILD.singleChoiceInteractions = [];

// SubContentIds - avoid duplicated answered statement
ILD.subIds = [];

// Stores QuestionSet PassPercentage
ILD.questionSetPassPercentage = [];

// Stores Essay PassPercentage
ILD.EssayPassPercentage = [];

// Stores Branching scenario info
ILD.BranchingScenario = [];

// Stores maxScore of interactions
ILD.maxScore = 0;

// Stores score of interactions
ILD.score = 0;

// Stores percentage of interactions progress
ILD.percentage = 0;

// Internal H5P function listening for xAPI answered events and stores scores
ILD.xAPIAnsweredListener = (event) => {
  const contentId = event.getVerifiedStatementValue([
    'object',
    'definition',
    'extensions',
    'http://h5p.org/x-api/h5p-local-content-id',
  ]);
  let isInteraction = false;

  if (event.data.statement.object.objectType === 'Activity') {
    isInteraction = true;
  }

  if (
    isInteraction &&
    event.getVerb() === 'answered' &&
    typeof ILD.questionSetPassPercentage[contentId] === 'undefined' &&
    typeof ILD.singleChoiceInteractions[contentId] === 'undefined' &&
    typeof ILD.EssayPassPercentage[contentId] === 'undefined' &&
    typeof ILD.BranchingScenario[contentId] === 'undefined'
  ) {
    const score = event.getScore();
    const maxScore = event.getMaxScore();
    let subContentId = event.data.statement.object.id.split('subContentId=')[1];

    if (ILD.subIds.indexOf(subContentId) !== -1) {
      if (typeof ILD.interactions[contentId] === 'undefined') {
        ILD.interactions[contentId] = 1;
      }

      const interactions = ILD.interactions[contentId];
      ILD.percentage += (score / maxScore / interactions) * 100;
      ILD.setResult(contentId, ILD.percentage, 100);
    } else if (ILD.subIds.indexOf(subContentId) === -1 && ILD.subIds.length === 0) {
      const percentage = (score / maxScore) * 100;
      ILD.setResult(contentId, percentage, 100);
    }
  }

  // Handle QuestionSet completion
  if (typeof ILD.questionSetPassPercentage[contentId] !== 'undefined' && event.getVerb() === 'completed') {
    const score = event.getScore();
    const maxScore = event.getMaxScore();
    const percentage = (score / maxScore) * 100;
    const passPercentage = ILD.questionSetPassPercentage[contentId];

    if (percentage >= passPercentage) {
      ILD.setResult(contentId, 100, 100);
    } else {
      ILD.setResult(contentId, percentage, 100);
    }
  }

  // Handle Essay score
  if (typeof ILD.EssayPassPercentage[contentId] !== 'undefined' && event.getVerb() === 'scored') {
    const score = event.getScore();
    const maxScore = event.getMaxScore();
    const percentage = (score / maxScore) * 100;
    ILD.setResult(contentId, percentage, 100);
  }

  // Handle SingleChoiceSet completion
  if (typeof ILD.singleChoiceInteractions[contentId] !== 'undefined' && event.getVerb() === 'completed') {
    const score = event.getScore();
    const maxScore = event.getMaxScore();
    const percentage = (score / maxScore) * 100;
    ILD.setResult(contentId, percentage, 100);
  }

  // Handle BranchingScenario completion
  if (typeof ILD.BranchingScenario[contentId] !== 'undefined' && event.getVerb() === 'completed') {
    ILD.setResult(contentId, 100, 100);
  }
};

// Post answered results for user and set progress
ILD.setResult = (contentId, score, maxScore) => {
  window.console.log(score);
  ILD.reactive.dispatch(
          "updateSectionprogress",
          ILD.sectionId,
          contentId,
          score,
          maxScore
        );
  // ajax.call([
  //   {
  //     methodname: 'format_mooin4_setgrade',
  //     args: { contentid: contentId, score, maxscore: maxScore },
  //   },
  // ]);
};

// Count interactions layers from interactive video element
ILD.getVideoInteractions = (contentId, content) => {
  const interactions = content.interactiveVideo.assets.interactions;
  const summaries = content.interactiveVideo.summary.task.params.summaries;
  const notAllowedInteractions = ['H5P.Text', 'H5P.Table', 'H5P.Link', 'H5P.Image', 'H5P.GoToQuestion', 'H5P.Nil', 'H5P.IVHotspot'];

  let interactionsCounter = 0;

  if (typeof interactions === 'object') {
    $.each(interactions, (i) => {
      const library = interactions[i].action.library;
      const subid = interactions[i].action.subContentId;

      if (!notAllowedInteractions.some((item) => library.includes(item))) {
        interactionsCounter++;
        ILD.subIds.push(subid);
      }
    });

    ILD.interactions[contentId] = interactionsCounter;
  }

  if (!interactions || (typeof interactions === 'object' && interactionsCounter === 0)) {
    $('.h5p-iframe')[0].contentWindow.onload = () => {
      $('.h5p-iframe')[0].contentWindow.H5P.instances[0].video.on('stateChange', (event) => {
        if (event.data === 0) {
          ILD.setResult(contentId, 100, 100);
        }
      });
    };
  }

  if (summaries.length) {
    let summary = false;

    $.each(summaries, (s) => {
      if (typeof summaries[s].summary !== 'undefined') {
        const subId = content.interactiveVideo.summary.task.subContentId;
        ILD.subIds.push(subId);
        summary = true;
      }
    });

    if (summary) {
      ILD.interactions[contentId] = interactionsCounter + 1;
    }
  }
};

// Count interactions layers from SingleChoice element
ILD.getSingleChoiceInteractions = (contentId, content) => {
  const interactions = content.choices;

  $.each(interactions, (s) => {
    const subid = interactions[s].subContentId;
    ILD.subIds.push(subid);
  });

  ILD.singleChoiceInteractions[contentId] = interactions.length;
};

// Get QuestionSet PassPercentage
ILD.getQuestionSetPercentage = (contentId, content) => {
  ILD.questionSetPassPercentage[contentId] = content.passPercentage;
};

// Get Essay PassPercentage
ILD.getEssayPercentage = (contentId, content) => {
  ILD.EssayPassPercentage[contentId] = content.behaviour.percentagePassing;
};

// Check if library is InteractiveVideo or QuestionSet
ILD.checkLibrary = (H5PIntegration, H5PInstance) => {
  
  window.console.log(H5PInstance);
  const contentId = H5PInstance.contentId;
 

  if (typeof contentId !== 'undefined') {
    const contentData = H5PIntegration.contents[`cid-${contentId}`];
    const content = JSON.parse(contentData.jsonContent);
    const library = contentData.library;
    

    if (library.includes('H5P.InteractiveVideo')) {
      ILD.getVideoInteractions(contentId, content);
    } else if (library.includes('H5P.QuestionSet')) {
      ILD.getQuestionSetPercentage(contentId, content);
    } else if (library.includes('H5P.SingleChoiceSet')) {
      ILD.getSingleChoiceInteractions(contentId, content);
    } else if (library.includes('H5P.Essay')) {
      ILD.getEssayPercentage(contentId, content);
    } else if (library.includes('H5P.BranchingScenario')) {
      ILD.BranchingScenario[contentId] = 1;
    }
  }
};


export default {
  init(H5PInstance, H5PIntegration, sectionId, reactive) {
    ILD.sectionId = sectionId;
    ILD.reactive = reactive;
    ILD.checkLibrary(H5PIntegration, H5PInstance.instances[0]);
    H5PInstance.externalDispatcher.on('xAPI', ILD.xAPIAnsweredListener);
  },
};
