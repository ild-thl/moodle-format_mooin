// Import der Abhängigkeiten
import $ from 'jquery';
import ajax from 'core/ajax';
import notification from 'core/notification';
import Str from 'core/str';
import Url from 'core/url';

// ILD Namespace Definition
const ILD = {};

// Interactions counter
ILD.interactions = [];

// SingleChoiceInteractions counter
ILD.singleChoiceInteractions = [];

// SubContentIds - pro Inhalt, um doppelte Antworten zu vermeiden
ILD.subIds = [];

// Mapping zwischen ContentId und Course Module Id
ILD.contentToCm = [];
ILD.contentToSection = [];
ILD.currentSectionOverride = null;

// Stores QuestionSet PassPercentage
ILD.questionSetPassPercentage = [];

// Stores Essay PassPercentage
ILD.EssayPassPercentage = [];

// Stores Branching scenario info
ILD.BranchingScenario = [];

// *** NEU HINZUGEFÜGT (Schritt 1) ***
// Merkt sich, welche Sub-Interaktionen pro Inhalt bereits beantwortet wurden
ILD.answeredSubIds = [];

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

    if (subContentId && subContentId.indexOf('&') !== -1) {
      subContentId = subContentId.split('&')[0];
    }

    // *** START ÄNDERUNG (Schritt 2) ***
    // Dieser Block wurde ersetzt
    const contentSubIds = ILD.subIds[contentId] || [];

    if (contentSubIds.indexOf(subContentId) !== -1) {
      if (!ILD.answeredSubIds[contentId]) {
        ILD.answeredSubIds[contentId] = [];
      }

      const answered = ILD.answeredSubIds[contentId];

      if (answered.indexOf(subContentId) === -1) {
        answered.push(subContentId);
      }

      const interactions = ILD.interactions[contentId];
      const answeredCount = answered.length;

      if (interactions && interactions > 0) {
        ILD.percentage = (answeredCount / interactions) * 100;
        ILD.setResult(contentId, answeredCount, interactions, ILD.contentToSection[contentId] || null);
      }
    } else if (!contentSubIds.length) {
      const percentage = (score / maxScore) * 100;
      ILD.setResult(contentId, percentage, 100, ILD.contentToSection[contentId] || null);
    }
    // *** ENDE ÄNDERUNG (Schritt 2) ***
  }

  // Handle QuestionSet completion
  if (typeof ILD.questionSetPassPercentage[contentId] !== 'undefined' && event.getVerb() === 'completed') {
    const score = event.getScore();
    const maxScore = event.getMaxScore();
    const percentage = (score / maxScore) * 100;
    const passPercentage = ILD.questionSetPassPercentage[contentId];

    if (percentage >= passPercentage) {
      ILD.setResult(contentId, 100, 100, ILD.contentToSection[contentId] || null);
    } else {
      ILD.setResult(contentId, percentage, 100, ILD.contentToSection[contentId] || null);
    }
  }

  // Handle Essay score
  if (typeof ILD.EssayPassPercentage[contentId] !== 'undefined' && event.getVerb() === 'scored') {
    const score = event.getScore();
    const maxScore = event.getMaxScore();
    const percentage = (score / maxScore) * 100;
    ILD.setResult(contentId, percentage, 100, ILD.contentToSection[contentId] || null);
  }

  // Handle SingleChoiceSet completion
  if (typeof ILD.singleChoiceInteractions[contentId] !== 'undefined' && event.getVerb() === 'completed') {
    const score = event.getScore();
    const maxScore = event.getMaxScore();
    const percentage = (score / maxScore) * 100;
    ILD.setResult(contentId, percentage, 100, ILD.contentToSection[contentId] || null);
  }

  // Handle BranchingScenario completion
  if (typeof ILD.BranchingScenario[contentId] !== 'undefined' && event.getVerb() === 'completed') {
    ILD.setResult(contentId, 100, 100, ILD.contentToSection[contentId] || null);
  }
};

// Post answered results for user and set progress
ILD.setResult = (contentId, score, maxScore, sectionIdOverride = null) => {
  const cmid = ILD.contentToCm[contentId] || null;
  const targetSectionId = sectionIdOverride || ILD.currentSectionOverride || ILD.sectionId;
  // Dispatch to state manager - it will calculate and update the correct overall progress
  ILD.reactive.dispatch(
    "updateSectionprogress",
    targetSectionId,
    contentId,
    score,
    maxScore,
    cmid
  );
  // Don't update DOM directly here - let the state manager handle it after backend calculation
};


// Check if library is InteractiveVideo or QuestionSet
ILD.checkLibrary = (H5PIntegration, H5PInstance) => {

  //window.console.log(H5PInstance);
  const contentId = H5PInstance.contentId;

  //fix
  //progress trigger for hvp videos without interactions or without answerable interactions when video ends
  if (H5PInstance && H5PInstance.video && typeof H5PInstance.video.on === 'function') {

    // Check if there are no answerable interactions in video
    function noAnswerableInteractions(H5PInstance) {
      return H5PInstance.interactions.every(interaction => !interaction.isAnswerable());
    }

    //console.log(`H5P Interactive Video instance (${contentId}) detected. Attaching video listener.`);
    if (H5PInstance.interactions
      && Array.isArray(H5PInstance.interactions)
      && H5PInstance.interactions.length === 0
      || noAnswerableInteractions(H5PInstance)) {

      H5PInstance.video.on('stateChange', function (event) {
        //console.log(`Video stateChange event for contentId ${contentId}:`, event.data);
        // stateChange data === 0 bedeutet Video beendet
        if (event.data === 0) {
          console.log(`✅ Video with contentId ${contentId} finished.`);
          ILD.setResult(contentId, 100, 100);
        }
      });


    }
  }

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


// Count interactions layers from interactive video element
ILD.getVideoInteractions = (contentId, content) => {
  const interactions = content.interactiveVideo.assets.interactions;
  const summaries = content.interactiveVideo.summary.task.params.summaries;
  const notAllowedInteractions = ['H5P.Text', 'H5P.Table', 'H5P.Link', 'H5P.Image', 'H5P.GoToQuestion', 'H5P.Nil', 'H5P.IVHotspot'];

  let interactionsCounter = 0;

  ILD.subIds[contentId] = [];

  if (typeof interactions === 'object') {
    $.each(interactions, (i) => {
      const library = interactions[i].action.library;
      const subid = interactions[i].action.subContentId;

      if (!notAllowedInteractions.some((item) => library.includes(item))) {
        interactionsCounter++;
        ILD.subIds[contentId].push(subid);
      }
    });

    ILD.interactions[contentId] = interactionsCounter;
  }



  /*
  if (!interactions || (typeof interactions === 'object' && interactionsCounter === 0)) {
    $('.h5p-iframe')[0].contentWindow.onload = () => {
      $('.h5p-iframe')[0].contentWindow.H5P.instances[0].video.on('stateChange', (event) => {
        if (event.data === 0) {
          ILD.setResult(contentId, 100, 100);
        }
      });
    };
  }
    */

  if (summaries.length) {
    let summary = false;

    $.each(summaries, (s) => {
      if (typeof summaries[s].summary !== 'undefined') {
        const subId = content.interactiveVideo.summary.task.subContentId;
        ILD.subIds[contentId].push(subId);
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

  ILD.subIds[contentId] = [];

  $.each(interactions, (s) => {
    const subid = interactions[s].subContentId;
    ILD.subIds[contentId].push(subid);
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




export default {
  init(H5PInstance, H5PIntegration, sectionId, reactive, cmid = null, sectionIdOverride = null) {
    ILD.sectionId = sectionId;
    ILD.reactive = reactive;
    const instance = H5PInstance.instances && H5PInstance.instances[0];
    if (instance && typeof instance.contentId !== 'undefined' && cmid) {
      ILD.contentToCm[instance.contentId] = cmid;
      const targetSection = sectionIdOverride || sectionId;
      ILD.contentToSection[instance.contentId] = targetSection;
    }
    ILD.checkLibrary(H5PIntegration, H5PInstance.instances[0]);
    H5PInstance.externalDispatcher.on('xAPI', (event) => {
      if (sectionIdOverride) {
        ILD.currentSectionOverride = sectionIdOverride;
      } else {
        ILD.currentSectionOverride = null;
      }
      ILD.xAPIAnsweredListener(event);
    });
  },
};