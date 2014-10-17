<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2013-2014 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 *
 * @author Jérôme Bogaerts <jerome@taotesting.com>
 * @license GPLv2
 *
 */

namespace qtism\runtime\tests;

use qtism\common\utils\Time;
use qtism\data\processing\ResponseProcessing;
use qtism\common\collections\Container;
use qtism\common\datatypes\Identifier;
use qtism\common\datatypes\Integer;
use qtism\data\IAssessmentItem;
use qtism\data\expressions\Correct;
use qtism\runtime\expressions\CorrectProcessor;
use qtism\runtime\expressions\ExpressionProcessingException;
use qtism\data\NavigationMode;
use qtism\data\SubmissionMode;
use qtism\data\TimeLimits;
use qtism\runtime\processing\ResponseProcessingEngine;
use qtism\runtime\common\OutcomeVariable;
use qtism\data\ItemSessionControl;
use qtism\common\datatypes\Duration;
use qtism\common\enums\BaseType;
use qtism\common\enums\Cardinality;
use qtism\runtime\common\ResponseVariable;
use qtism\runtime\common\State;
use \DateTime;
use \InvalidArgumentException;

/**
 * The AssessmentItemSession class implements the lifecycle of an AssessmentItem session.
 *
 * When instantiated, the resulting AssessmentItemSession object is initialized in
 * the in the AssessmentItemSessionState::INITIAL state, and the session begins (a call
 * to AssessmentItemSession::beginItemSession is performed).
 *
 * FROM IMS QTI:
 *
 * An item session is the accumulation of all the attempts at a particular instance of an
 * item made by a candidate. In some types of test, the same item may be presented to the
 * candidate multiple times (e.g., during 'drill and practice'). Each occurrence or
 * instance of the item is associated with its own item session.
 *
 * The following diagram illustrates the user-perceived states of the item session. Not all
 * states will apply to every scenario, for example feedback may not be provided for an
 * item or it may not be allowed in the context in which the item is being used. Similarly,
 * the candidate may not be permitted to review their responses and/or examine a model
 * solution. In practice, systems may support only a limited number of the indicated state
 * transitions and/or support other state transitions not shown here.
 *
 * For system developers, an important first step in determining which requirements apply
 * to their system is to identify which of the user-perceived states are supported in their
 * system and to match the state transitions indicated in the diagram to their own event
 * model.
 *
 * The discussion that follows forms part of this specification's requirements on Delivery
 * Engines.
 *
 * The session starts when the associated item first becomes eligible for delivery to the
 * candidate. The item session's state is then maintained and updated in response to the
 * actions of the candidate until the session is over. At any time the state of the session
 * may be turned into an itemResult. A delivery system may also allow an itemResult to be
 * used as the basis for a new session in order to allow a candidate's responses to be seen
 * in the context of the item itself (and possibly compared to a solution) or even to allow
 * a candidate to resume an interrupted session at a later time.
 *
 * The initial state of an item session represents the state after it has been determined
 * that the item will be delivered to the candidate but before the delivery has taken
 * place.
 *
 * In a typical non-Adaptive Test the items are selected in advance and the candidate's
 * interaction with all items is reported at the end of the test session, regardless of
 * whether or not the candidate actually attempted all the items. In effect, item sessions
 * are created in the initial state for all items at the start of the test and are
 * maintained in parallel. In an Adaptive Test the items that are to be presented are
 * selected during the session based on the responses and outcomes associated with the
 * items presented so far. Items are selected from a large pool and the delivery engine
 * only reports the candidate's interaction with items that have actually been selected.
 *
 * A candidate's interaction with an item is broken into 0 or more attempts. During each
 * attempt the candidate interacts with the item through one or more candidate sessions.
 * At the end of a candidate session the item may be placed into the suspended state ready
 * for the next candidate session. During a candidate session the item session is in the
 * interacting state. Once an attempt has ended response processing takes place, after
 * response processing a new attempt may be started.
 *
 * For non-adaptive items, response processing typically takes place a limited number of
 * times, usually only once. For adaptive items, no such limit is required because the
 * response processing adapts the values it assigns to the outcome variables based on the
 * path through the item. In both cases, each invocation of response processing occurrs at
 * the end of each attempt. The appearance of the item's body, and whether any modal
 * feedback is shown, is determined by the values of the outcome variables.
 *
 * When no more attempts are allowed the item session passes into the closed state. Once in
 * the closed state the values of the response variables are fixed. A delivery system or
 * reporting tool may still allow the item to be presented after it has reached the closed
 * state. This type of presentation takes place in the review state, summary feedback may
 * also be visible at this point if response processing has taken place and set a suitable
 * outcome variable.
 *
 * Finally, for systems that support the display of solutions, the item session may pass
 * into the solution state. In this state, the candidate's responses are temporarily
 * replaced by the correct values supplied in the corresponding responseDeclarations
 * (or NULL if none was declared).
 *
 * @author Jérôme Bogaerts <jerome@taotesting.com>
 * @see http://www.imsglobal.org/question/qtiv2p1/imsqti_infov2p1.html#section10055 The IMS QTI 2.1 Item Session Lifecycle.
 */
class AssessmentItemSession extends State
{
    /**
     * The item completion status 'incomplete'.
     *
     * @var string
     */
    const COMPLETION_STATUS_INCOMPLETE = 'incomplete';

    /**
     * The item completion status 'not_attempted'.
     *
     * @var string
     */
    const COMPLETION_STATUS_NOT_ATTEMPTED = 'not_attempted';

    /**
     * The item completion status 'unknown'.
     *
     * @var string
     */
    const COMPLETION_STATUS_UNKNOWN = 'unknown';

    /**
     * The item completion status 'completed'.
     *
     * @var string
     */
    const COMPLETION_STATUS_COMPLETED = 'completed';

    /**
     * A timing reference used to compute the duration of
     * the session.
     *
     * @var DateTime
     */
    private $timeReference = null;

    /**
	 * The state of the Item Session as described
	 * by the AssessmentItemSessionState enumeration.
	 *
	 * @var integer
	 */
    private $state = AssessmentItemSessionState::NOT_SELECTED;

    /**
	 * The ItemSessionControl object giving information about how to control
	 * the session.
	 *
	 * @var ItemSessionControl
	 */
    private $itemSessionControl;

    /**
	 * The time limits to be applied on the session if
	 * needed.
	 *
	 * @var TimeLimits
	 */
    private $timeLimits = null;

    /**
	 * The navigation mode in use during the item session.
	 * Default is NavigationMode::LINEAR.
	 *
	 * @var integer
	 */
    private $navigationMode = NavigationMode::LINEAR;

    /**
	 * The submission mode in use during the item session.
	 * Default is SubmissionMode::INDIVIDUAL.
	 *
	 * @var integer
	 */
    private $submissionMode = SubmissionMode::INDIVIDUAL;

    /**
	 * The ExtendedAssessmentItemRef describing the item the session
	 * handles.
	 *
	 * @var IAssessmentItem
	 */
    private $assessmentItem;

    /**
	 * Whether or not the session (SUSPENDED or INTERACTING) is
	 * currently attempting an attempt. In other words, a candidate
	 * begun an attempt and did not ended it yet.
	 *
	 * @var boolean
	 */
    private $attempting = false;

    /**
	 *
	 * @var \qtism\runtime\tests\AbstractSessionManager
	 */
    private $sessionManager;

    /**
	 * Create a new AssessmentItemSession object. 
	 * 
	 * * The built-in response variables 'numAttempts' and 'duration' will be created and set up with appropriate default values, respectively Integer(0) and Duration('PT0S').
	 * * The built-in outcome variable 'completionStatus' will be created and set up with an appropriate default value of  String('not_attempted').
	 * * The item session is set up with a default ItemSessionControl object. If you want a specific ItemSessionControl object to rule the session, use the setItemSessionControl() method.
	 * * The item session is set up with no TimeLimits object. If you want to set a a specfici TimeLimits object to rule the session, use the setTimeLimits() method.
	 *
	 * @param \qtism\data\IAssessmentItem $assessmentItem The description of the item that the session handles.
	 * @param \qtism\runtime\tests\AbstractSessionManager $sessionManager A SessionManager object.
	 * @throws \InvalidArgumentException If $navigationMode or $submission is not a value from the NavigationMode/SubmissionMode enumeration.
	 * @see \qtism\runtime\tests\AssessmentItemSession::setItemSessionControl() The setItemSessionControl() method.
	 * @see \qtism\runtime\tests\AssessmentItemSession::setTimeLimits() The setTimeLimits() method.
	 */
    public function __construct(IAssessmentItem $assessmentItem, AbstractSessionManager $sessionManager)
    {
        parent::__construct();

        $this->setAssessmentItem($assessmentItem);
        $this->setItemSessionControl(new ItemSessionControl());
        $this->setSessionManager($sessionManager);

        // -- Create the built-in response variables.
        $this->setVariable(new ResponseVariable('numAttempts', Cardinality::SINGLE, BaseType::INTEGER, new Integer(0)));
        $this->setVariable(new ResponseVariable('duration', Cardinality::SINGLE, BaseType::DURATION, new Duration('PT0S')));

        // -- Create the built-in outcome variables.
        $this->setVariable(new OutcomeVariable('completionStatus', Cardinality::SINGLE, BaseType::IDENTIFIER, new Identifier(self::COMPLETION_STATUS_NOT_ATTEMPTED)));
    }

    /**
	 * Set the state of the current AssessmentItemSession. The state of the session is a value
	 * from the AssessmentItemSessionState enumeration.
	 *
	 * @param integer $state A value from the AssessmentItemSessionState enumeration.
	 * @see \qtism\runtime\tests\AssessmentItemSessionState The AssessmentItemSessionState enumeration.
	 */
    public function setState($state)
    {
        $this->state = $state;
    }

    /**
	 * Get the state of the current AssessmentItemSession. The state of the session is a value
	 * from the AssessmentItemSessionState enumeration.
	 *
	 * @return integer A value from the AssessmentItemSessionState enumeration.
	 * @see \qtism\runtime\tests\AssessmentItemSessionState The AssessmentItemSessionState enumeration.
	 */
    public function getState()
    {
        return $this->state;
    }

    /**
	 * Set the ItemSessionControl object which describes the way to control
	 * the item session. If the current session is in a SIMULTANEOUS submission
	 * mode context, the maxAttempt attribute of $itemSessionControl is
	 * automatically set to 1.
	 *
	 * @param \qtism\data\ItemSessionControl $itemSessionControl An ItemSessionControl object.
	 */
    public function setItemSessionControl(ItemSessionControl $itemSessionControl)
    {
        $this->itemSessionControl = $itemSessionControl;
    }

    /**
	 * Get the ItemSessionControl object which describes the way to control the item session.
	 *
	 * @return \qtism\data\ItemSessionControl An ItemSessionControl object.
	 */
    public function getItemSessionControl()
    {
        return $this->itemSessionControl;
    }

    /**
	 * Set the TimeLimits to be applied to the session.
	 *
	 * @param \qtism\data\TimeLimits $timeLimits A TimeLimits object or null if no time limits must be applied.
	 */
    public function setTimeLimits(TimeLimits $timeLimits = null)
    {
        $this->timeLimits = $timeLimits;
    }

    /**
	 * Get the TimeLimits to be applied to the session.
	 *
	 * @return \qtism\data\TimeLimits A TimLimits object or null if no time limits must be applied.
	 */
    public function getTimeLimits()
    {
        return $this->timeLimits;
    }

    /**
	 * Set the timing reference. The time reference is used to inform the session
	 * "what time it is" prior to interacting with it.
	 *
	 * @param \DateTime $timeReference A DateTime object.
	 */
    public function setTimeReference(DateTime $timeReference)
    {
        $this->timeReference = $timeReference;
    }

    /**
	 * Get the timing reference. The time reference is used to inform the session
	 * "what time it is" prior to interacting with it.
	 *
	 * @return \DateTime A DateTime object.
	 */
    public function getTimeReference()
    {
        return $this->timeReference;
    }

    /**
	 * Whether or not a timing reference is defined for this item session.
	 *
	 * @return boolean
	 */
    public function hasTimeReference()
    {
        return $this->timeReference !== null;
    }

    /**
	 * Whether or not minimum time limits must be taken into account.
	 *
	 * @return boolean
	 */
    protected function mustConsiderMinTime()
    {
        return $this->getSessionManager()->mustConsiderMinTime();
    }

    /**
	 * Whether the session is driven by a TimeLimits object
	 * or not.
	 *
	 * @return boolean
	 */
    public function hasTimeLimits()
    {
        return $this->getTimeLimits() !== null;
    }

    /**
	 * Set the navigation mode in use during the item session.
	 *
	 * @param integer $navigationMode A value from the NavigationMode enumeration.
	 * @see \qtism\data\NavigationMode The NavigationMode enumeration.
	 */
    public function setNavigationMode($navigationMode)
    {
        $this->navigationMode = $navigationMode;
    }

    /**
	 * Get the navigation mode in use during the item session.
	 *
	 * @return integer A value from the NavigationMode enumeration.
	 * @see \qtism\data\NavigationMode The NavigationMode enumeration.
	 */
    public function getNavigationMode()
    {
        return $this->navigationMode;
    }

    /**
	 * Set the submission mode in use during the item session.
	 *
	 * @param integer $submissionMode A value from the SubmissionMode enumeration.
	 * @see \qtism\data\SubmissionMode The SubmissionMode enumeration.
	 */
    public function setSubmissionMode($submissionMode)
    {
        $this->submissionMode = $submissionMode;
    }

    /**
	 * Get the submission mode in use during the item session.
	 *
	 * @return integer A value from the SubmissionMode enumeration.
	 * @see \qtism\data\SubmissionMode The SubmissionMode enumeration.
	 */
    public function getSubmissionMode()
    {
        return $this->submissionMode;
    }

    /**
	 * Convenience method.
	 *
	 * Whether the navigation mode in use for the item session is LINEAR.
	 *
	 * @return boolean
	 * @see \qtism\data\NavigationMode The NavigationMode enumeration.
	 */
    public function isNavigationLinear()
    {
        return $this->getNavigationMode() === NavigationMode::LINEAR;
    }

    /**
	 * Convenience method.
	 *
	 * Whether the navigation mode in use for the item session is NON_LINEAR.
	 *
	 * @return boolean
	 * @see \qtism\data\NavigationMode The NavigationMode enumeration.
	 */
    public function isNavigationNonLinear()
    {
        return $this->getNavigationMode() === NavigationMode::NONLINEAR;
    }

    /**
	 * Set the IAssessmentItem object which describes the item to be handled
	 * by the session.
	 *
	 * @param \qtism\data\IAssessmentItem $assessmentItem An IAssessmentItem object.
	 */
    public function setAssessmentItem(IAssessmentItem $assessmentItem)
    {
        $this->assessmentItem = $assessmentItem;
    }

    /**
	 * Get the IAssessmentItem object which describes the item to be handled by the
	 * session.
	 *
	 * @return \qtism\data\IAssessmentItem An IAssessmentItem object.
	 */
    public function getAssessmentItem()
    {
        return $this->assessmentItem;
    }

    /**
	 * Set whether a candidate is currently performing an attempt.
	 *
	 * @param boolean $attempting
	 * @throws \InvalidArgumentException If $attempting is not a boolean value.
	 */
    public function setAttempting($attempting)
    {
        $this->attempting = $attempting;
    }

    /**
	 * Whether the candidate is currently performing an attempt. A candidate
	 * can be performing an attempt, even if the session is closed. In this situation,
	 * it means that the candidate was interacting with the item, but went in suspend
	 * state by ending the candidate session rather than ending the attempt.
	 *
	 * @return boolean
	 */
    public function isAttempting()
    {
        return $this->attempting;
    }

    /**
	 * Get the session manager.
	 *
	 * @return \qtism\runtime\tests\AbstractSessionManager
	 */
    protected function getSessionManager()
    {
        return $this->sessionManager;
    }

    /**
	 * Set the session manager.
	 *
	 * @param \qtism\runtime\tests\AbstractSessionManager $sessionManager
	 */
    protected function setSessionManager(AbstractSessionManager $sessionManager)
    {
        $this->sessionManager = $sessionManager;
    }

    /**
	 * Set the current time of the running assessment item session. If the session
	 * is in INTERACTING mode, the difference between the last time reference provided
	 * with the previous call on the setTime() method and $time will be computed. This
	 * time difference will be added to the current value of the built-in outcome variable
	 * 'duration'.
	 * 
	 * If the value of the built-in outcome variable 'duration' exceeds the maximum time limit
	 * in force, the session will be closed by performing an internal call to the endItemSession()
	 * method.
	 *
	 * @param \DateTime $time The current time that will be taken into account for all next interactions with the object.
	 * @see \qtism\runtime\tests\AssessmentItemSession::endItemSession() The endItemSession() method.
	 */
    public function setTime(DateTime $time)
    {
        // Force time to be UTC.
        $time = Time::toUtc($time);

        if ($this->hasTimeReference() === true) {

            if ($this->getState() === AssessmentItemSessionState::INTERACTING) {
                // The session state is INTERACTING. Thus, we need to update the built-in
                // duration variable.
                $diffSeconds = Time::timeDiffSeconds($this->getTimeReference(), $time);
                $diffDuration = new Duration("PT${diffSeconds}S");
                $this['duration']->add($diffDuration);
            }

            if ($this->isMaxTimeReached() === true) {
                // -- Maximum time is reached, close the session.

                // Limit duration to max time if needed.
                $tl = $this->getTimeLimits();
                if (($maxTime = $tl->getMaxTime()) !== null && $maxTime->shorterThan($this['duration']) === true) {
                    $newDuration = clone $maxTime;
                    $this['duration'] = $newDuration;
                }

                $this->endItemSession();
            }
        }

        // Update reference time with $time.
        $this->setTimeReference($time);
    }

    /**
	 * Start the item session. The item session must be started when the related item becomes eligible for the candidate.
	 *
	 * * Response variables involved in the session will be set a value of NULL.
	 * * Outcome variables involved in the session will be set their default value if any. Otherwise, they are set to NULL unless their baseType is integer or float. In this case, the value is 0 or 0.0.
	 * * The state of the session is set to INITIAL.
	 *
	 * @see http://www.imsglobal.org/question/qtiv2p1/imsqti_infov2p1.html#section10055 The IMS QTI 2.1 Item Session Lifecycle.
	 */
    public function beginItemSession()
    {
        // We initialize the item session and its variables.
        foreach ($this->getAssessmentItem()->getOutcomeDeclarations() as $outcomeDeclaration) {
            // Outcome variables are instantiantiated as part of the item session.
            // Their values may be initialized with a default value if they have one.
            $outcomeVariable = OutcomeVariable::createFromDataModel($outcomeDeclaration);
            $outcomeVariable->initialize();
            $outcomeVariable->applyDefaultValue();
            $this->setVariable($outcomeVariable);
        }

        foreach ($this->getAssessmentItem()->getResponseDeclarations() as $responseDeclaration) {
            // Response variables are instantiated as part of the item session.
            // Their values are always initialized to NULL.
            $responseVariable = ResponseVariable::createFromDataModel($responseDeclaration);
            $responseVariable->initialize();
            $this->setVariable($responseVariable);
        }

        // The session gets the INITIAL state, ready for a first attempt.
        $this->setState(AssessmentItemSessionState::INITIAL);
        $this['duration'] = new Duration('PT0S');
        $this['numAttempts']->setValue(0);
        $this['completionStatus']->setValue(self::COMPLETION_STATUS_NOT_ATTEMPTED);
    }

    /**
	 * begin an attempt for this item session. The value of the built-in outcome variable 'completionStatus' is set to the 'unknown' value at
	 * the beginning of the very first attempt on this session.
	 *
	 * * If the attempt to begin is the first one of the session, response variables are applied their default value.
	 * * If the current submissionMode of the session is SIMULTANEOUS, only one call to beginAttempt() is allowed, otherwise an exception will be thrown.
	 *
	 * @throws \qtism\runtime\tests\AssessmentItemSessionException If the maximum number of attempts or the maximum time limit in force is reached.
	 * @see http://www.imsglobal.org/question/qtiv2p1/imsqti_infov2p1.html#section10055 The IMS QTI 2.1 Item Session Lifecycle.
	 */
    public function beginAttempt()
    {
        $maxAttempts = $this->itemSessionControl->getMaxAttempts();
        $numAttempts = $this['numAttempts']->getValue();
        $submissionMode = $this->getSubmissionMode();
        
        /* If the current submission mode is SIMULTANEOUS, only 1 attempt is allowed per item.
         *
         * From IMS QTI:
         *
         * In simultaneous mode, response processing cannot take place until the testPart is
         * complete so each item session passes between the interacting and suspended states only.
         * By definition the candidate can take one and only one attempt at each item and feedback
         * cannot be seen during the test. Whether or not the candidate can return to review
         * their responses and/or any item-level feedback after the test, is outside the scope
         * of this specification. Simultaneous mode is typical of paper-based tests.
         *
         */
        if ($submissionMode === SubmissionMode::SIMULTANEOUS) {
            $maxAttempts = 1;
        }

        // Check if we can perform a new attempt.
        if ($this->getState() === AssessmentItemSessionState::CLOSED) {
            if ($submissionMode === SubmissionMode::SIMULTANEOUS && $numAttempts > 0) {
                $identifier = $this->getAssessmentItem()->getIdentifier();
                $msg = "A new attempt for item '${identifier}' is not allowed. The submissionMode is simultaneous and the only accepted attempt is already begun.";
                throw new AssessmentItemSessionException($msg, $this, AssessmentItemSessionException::ATTEMPTS_OVERFLOW);
            }
            elseif ($submissionMode === SubmissionMode::INDIVIDUAL && $maxAttempts !== 0 && $numAttempts >= $maxAttempts) {
                $identifier = $this->getAssessmentItem()->getIdentifier();
                $msg = "A new attempt for item '${identifier}' is not allowed. The maximum number of attempts (${maxAttempts}) is reached.";
                throw new AssessmentItemSessionException($msg, $this, AssessmentItemSessionException::ATTEMPTS_OVERFLOW);
            }
            elseif ($this->isMaxTimeReached() === true) {
                $identifier = $this->getAssessmentItem()->getIdentifier();
                $msg = "A new attempt for item '${identifier}' is not allowed. The maximum time limit in force is reached.";
                throw new AssessmentItemSessionException($msg, $this, AssessmentItemSessionException::DURATION_OVERFLOW);
            }
        }
        
        
        // Response variables' default values are set at the beginning of the first attempt.
        if ($numAttempts === 0) {

            $data = &$this->getDataPlaceHolder();
            
            // At the start of the first attempt, the completionStatus goes to 'unknown' and response 
            // variables get their default values if any.

            foreach (array_keys($data) as $k) {
                if ($data[$k] instanceof ResponseVariable) {
                    $data[$k]->applyDefaultValue();
                }
            }

            $this['duration'] = new Duration('PT0S');
            $this['numAttempts'] = new Integer(0);
            
            // At the start of the first attempt, the completionStatus goes
            // to 'unknown'.
            $this['completionStatus']->setValue(self::COMPLETION_STATUS_UNKNOWN);
        }

        // Increment the built-in variable 'numAttempts' by one.
        $this['numAttempts']->setValue($numAttempts + 1);
        
        // The session get the INTERACTING state.
        $this->setState(AssessmentItemSessionState::INTERACTING);
        $this->setAttempting(true);
    }

    /**
	 * End the attempt by providing the responses of the candidate. If $responses is provided, the values found into it will be merged to 
	 * the current session, and response processing will take place.
	 *
	 * * After response processing, if the item is adaptive and the completionStatus is indicated to be 'completed', the item session ends.
	 * * After response processing, If the item is non-adaptive, and the maximum number of attempts is reached, the item session ends and the completionStatus is set to 'completed'.
	 * * Otherwise, the item session goes to the SUSPENDED state, waiting for a next attempt. If the item is non-adaptive, the completionStatus is set to 'completed'.
	 * 
	 * Please note that if the $responseProcessing argument is false, the response processing will not take place and the attempt will not be
	 * taken into account.
	 *
	 * @param \qtism\runtime\common\State $responses (optional) A State composed by the candidate's responses to the item.
	 * @param boolean $responseProcessing (optional) Whether to execute the responseProcessing or not.
	 * @param boolean $forceLateSubmission Force the acceptance of late response submission. In this case, responses that are received out of the time frame indicated by the time limits in force are accepted anyway.
	 * @throws \qtism\runtime\tests\AssessmentItemSessionException If the time limits in force are not respected, an error occurs during response processing, a state violation occurs.
	 */
    public function endAttempt(State $responses = null, $responseProcessing = true, $forceLateSubmission = false)
    {
        // Flag to indicate if time is exceed or not.
        $maxTimeExceeded = false;

        if ($this->getState() === AssessmentItemSessionState::CLOSED) {
            if ($this->isMaxTimeReached() === true && ($this->getTimeLimits()->doesAllowLateSubmission() === false && $forceLateSubmission === false)) {
                $msg = "The maximum time to be spent on the item session has been reached.";
                throw new AssessmentItemSessionException($msg, $this, AssessmentItemSessionException::DURATION_OVERFLOW);
            }
            // else...
            // We let it go to give a chance to responses to be processed if late submission is allowed.
        }

        // Do we have a minimum time limit to be respected?
        if ($this->hasTimeLimits() === true) {
            // As per QTI 2.1 Spec, Minimum times are only applicable to assessmentSections and
            // assessmentItems only when linear navigation mode is in effect.
            if ($this->isNavigationLinear() === true && $this->getTimeLimits()->hasMinTime() === true) {

                if ($this->mustConsiderMinTime() === true && $this['duration']->getSeconds(true) <= $this->getTimeLimits()->getMinTime()->getSeconds(true)) {
                    // An exception is thrown to prevent the numAttempts to be incremented.
                    // Suspend and wait for a next attempt.
                    $this->suspend();
                    $msg = "The minimal duration is not yet reached.";
                    throw new AssessmentItemSessionException($msg, $this, AssessmentItemSessionException::DURATION_UNDERFLOW);
                }
            }
        }
        
        // Apply the responses (if provided) to the current state.
        if ($responses !== null) {
            $this->mergeResponses($responses);
        }

        // Apply response processing.
        // As per QTI 2.1 specs, For Non-adaptive Items, the values of the outcome variables are reset to their
        // default values prior to each invocation of responseProcessing. For Adaptive Items the outcome variables
        // retain the values that were assigned to them during the previous invocation of response processing.
        // For more information, see Response Processing.
        //
        // The responseProcessing can be skipped by given a false value to $responseProcessing. Why?
        // Because when the SubmissionMode is SubmissionMode::SIMULTANEOUS, the responseProcessing must
        // deffered to the end of the current testPart.
        if ($responseProcessing === true) {

            if ($this->getAssessmentItem()->isAdaptive() === false) {
                $this->resetOutcomeVariables();
            }

            $rule = $this->getAssessmentItem()->getResponseProcessing();

            // Some items (especially to collect information) have no response processing!
            if ($rule !== null && ($rule->hasTemplate() === true || $rule->hasTemplateLocation() === true || count($rule->getResponseRules()) > 0)) {
                $engine = $this->createResponseProcessingEngine($rule);
                $engine->process();
            }
        }

        $maxAttempts = $this->getItemSessionControl()->getMaxAttempts();
        if ($this->getSubmissionMode() === SubmissionMode::SIMULTANEOUS) {
            $maxAttempts = 1;
        }

        // -- Adaptive item.
        if ($this->getAssessmentItem()->isAdaptive() === true && $this->getSubmissionMode() === SubmissionMode::INDIVIDUAL && $this['completionStatus']->getValue() === self::COMPLETION_STATUS_COMPLETED) {
            $this->endItemSession();
        }
        // -- Non-adaptive item + maxAttempts reached.
        elseif ($this->getAssessmentItem()->isAdaptive() === false && $this['numAttempts']->getValue() >= $maxAttempts) {

            // Close only if $maxAttempts !== 0 because 0 means no limit!
            //
            // + Special case, no response processing requested && simulatenous navigation mode
            // --> The session must not close prior to deferred response processing. The session goes
            // then in suspended mode.
            if ($maxAttempts !== 0 && $responseProcessing === true) {
                $this->endItemSession();
            }

            // Even if there is no limit of attempts, we consider the item completed.
            $this['completionStatus']->setValue(self::COMPLETION_STATUS_COMPLETED);
        }
        // -- Non-adaptive - remaining attempts.
        elseif ($this->getAssessmentItem()->isAdaptive() === false) {
            // Wait for the next attempt...
            $this['completionStatus']->setValue(self::COMPLETION_STATUS_COMPLETED);
        }

        // End of attempt, go in SUSPEND state (only if real endAttempt).
        if ($this->getState() !== AssessmentItemSessionState::CLOSED) {
            if ($responseProcessing === true) {
                // Real end attempt.
                $this->suspend();
            }
        }
    }
    
    /**
     * Merge $responses to the current values composing the item session.
     * 
     * @param State $responses
     */
    protected function mergeResponses(State $responses)
    {
        foreach ($responses as $identifier => $value) {
            $this[$identifier] = $value->getValue();
        }
    }

    /**
	 * Suspend the item session. The state will switch to SUSPENDED.
	 *
	 * @throws \qtism\runtime\tests\AssessmentItemSessionException With code STATE_VIOLATION if the state of the session is not INTERACTING nor MODAL_FEEDBACK prior to suspension.
	 */
    public function suspend()
    {
        $state = $this->getState();
        
        if ($state !== AssessmentItemSessionState::INTERACTING && $state !== AssessmentItemSessionState::MODAL_FEEDBACK) {
            $msg = "Cannot switch from state " . strtoupper(AssessmentItemSessionState::getNameByConstant($state)) . " to state SUSPENDED.";
            $code = AssessmentItemSessionException::STATE_VIOLATION;
            throw new AssessmentItemSessionException($msg, $this, $code);
        } else {
            $this->setState(AssessmentItemSessionState::SUSPENDED);
            $this->setAttempting(false);
        }
    }

    /**
	 * Indicate that the candidate is beginning the candidate session. In other words, the candidate makes
	 * the item session go from the SUSPENDED state to the INTERACTING state. To successfuly call this method
	 * without throwing an exception, the SUSPENDED state had to be set via a call to the endCandidateSession()
	 * method.
	 *
	 * @throws \qtism\runtime\tests\AssessmentItemSessionException With code STATE_VIOLATION if the state of the session is not SUSPENDED.
	 */
    public function beginCandidateSession()
    {
        $state = $this->getState();

        if ($state !== AssessmentItemSessionState::SUSPENDED) {
            $msg = "Cannot switch from state " . strtoupper(AssessmentItemSessionState::getNameByConstant($state)) . " to state INTERACTING.";
            $code = AssessmentItemSessionException::STATE_VIOLATION;
            throw new AssessmentItemSessionException($msg, $this, $code);
        } elseif ($this->isAttempting() === false) {
            $msg = "Cannot switch from state " . strtoupper(AssessmentItemSessionState::getNameByConstant($state)) . " to state INTERACTING while not currently attempting.";
            $code = AssessmentItemSessionException::STATE_VIOLATION;
            throw new AssessmentItemSessionException($msg, $this, $code);
        } else {
            $this->setState(AssessmentItemSessionState::INTERACTING);
        }
    }
    
    
    /**
     * Indicate that the candidate is ending its candidate session. In other words, the candidate makes the item session
     * go from the INTERACTING mode to the SUSPENDED mode, without ending the attempt. The attempt can be resumed by a
     * call to the beginCandidateSession() method.
     * 
     * @throws AssessmentItemSessionException If a state violation occurs.
     */
    public function endCandidateSession()
    {
        $state = $this->getState();
        
        if ($state !== AssessmentItemSessionState::INTERACTING) {
            $msg = "Cannot switch from state " . strtoupper(AssessmentItemSessionState::getNameByConstant($state)) . " to state SUSPENDED.";
            $code = AssessmentItemSessionException::STATE_VIOLATION;
            throw new AssessmentItemSessionException($msg, $this, $code);
        } else {
            $this->endAttempt(null, false);
            $this->setState(AssessmentItemSessionState::SUSPENDED);
        }
    }

    /**
	 * Get the time that remains to the candidate to submit its responses.
	 *
	 * @return false|\qtism\common\datatypes\Duration A Duration object or false if there is no time limit.
	 */
    public function getRemainingTime()
    {
        // false = unlimited
        $remainingTime = false;

        if ($this->hasTimeLimits() === true && $this->getTimeLimits()->hasMaxTime() === true) {
            $remainingTime = clone $this->getTimeLimits()->getMaxTime();
            $remainingTime->sub($this['duration']);
        }

        return $remainingTime;
    }

    /**
	 * Close the item session. The 'completionStatus' built-in outcome variable and the state
	 * of the item session goes to CLOSED.
	 */
    public function endItemSession()
    {
        // If the candidate was interacting, suspend before
        // to get a correct state flow.
        if ($this->getState() === AssessmentItemSessionState::INTERACTING) {
            $this->suspend();
        }

        $this->setState(AssessmentItemSessionState::CLOSED);
        $this->setAttempting(false);
    }

    /**
	 * Skip the item of the current item session. All response variables involved in the item session
	 * will be set to their default value or NULL and submitted.
	 *
	 * @throws \qtism\runtime\tests\AssessmentItemSessionException If skipping is not allowed with respect to the current itemSessionControl.
	 */
    public function skip()
    {
        // allowSkipping is taken into account only if submission mode is INDIVIDUAL.
        if ($this->getItemSessionControl()->doesAllowSkipping() === false && $this->getSubmissionMode() === SubmissionMode::INDIVIDUAL) {
            // Skipping not allowed.
            $itemIdentifier = $this->getAssessmentItem()->getIdentifier();
            $msg = "Skipping item '${itemIdentifier}' is not allowed.";
            throw new AssessmentItemSessionException($msg, $this, AssessmentItemSessionException::SKIPPING_FORBIDDEN);
        } else {
            // Detect all response variables and submit them with their default value or NULL.
            foreach ($this->getAssessmentItem()->getResponseDeclarations() as $responseDeclaration) {
                $this->getVariable($responseDeclaration->getIdentifier())->applyDefaultValue();
            }

            // End the attempt with a null state.
            $this->endAttempt(null, true);
        }
    }

    /**
	 * Get the number of remaining attempts possible for the item session.
	 * Be careful! If the item of the session is adaptive but not yet completed or if the maxAttempts is unlimited, -1 is returned
	 * because there is no way to determine how much remaining attempts are available.
	 *
	 * @return integer The number of remaining items. -1 means unlimited.
	 */
    public function getRemainingAttempts()
    {
        $itemRef = $this->getAssessmentItem();

        $maxAttempts = $this->getItemSessionControl()->getMaxAttempts();
        if ($this->getSubmissionMode() === SubmissionMode::SIMULTANEOUS) {
            $maxAttempts = 1;
        }

        if ($itemRef->isAdaptive() === false) {
            if ($maxAttempts === 0) {
                // 0 means unlimited.
                return -1;
            } elseif ($itemRef->isAdaptive() === false && $this->isMaxTimeReached() === false) {
                // The item is non-adaptative and is not completed nor time exceeded.
                return $maxAttempts - $this['numAttempts']->getValue();
            } else {
                return 0;
            }
        } elseif ($itemRef->isAdaptive() === true && $this['completionStatus']->getValue() === self::COMPLETION_STATUS_COMPLETED) {
            // The item is adaptive and completed.
            return 0;
        }

        // The item is adaptive, and is not completed yet.
        return -1;
    }

    /**
	 * Whether all non built-in response variables held by the session match their
	 * associated correct response.
	 *
	 * If the item session has the NOT_SELECTED state, false is directly returned because
	 * it is certain that there is no correct response yet in the session.
	 *
	 * @return boolean
	 * @throws \qtism\runtime\tests\AssessmentItemSessionException With error code = RUNTIME_ERROR if an error occurs while processing the 'correct' QTI expression on a response variable held by the session.
	 */
    public function isCorrect()
    {
        if ($this->getState() === AssessmentItemSessionState::NOT_SELECTED) {
            // The session cannot be considered as correct if not yet selected
            // for presentation to the candidate.
            return false;
        }

        $data = &$this->getDataPlaceHolder();
        $excludedVariableIdentifiers = array('numAttempts', 'duration');

        foreach (array_keys($data) as $identifier) {
            $var = $data[$identifier];

            if ($var instanceof ResponseVariable && in_array($var->getIdentifier(), $excludedVariableIdentifiers) === false) {

                // From IMS QTI:
                // Only items for which all declared response variables have correct responses defined are considered.
                if ($var->hasCorrectResponse() === false || $var->isCorrect() === false) {
                    return false;
                }
            }
        }

        // All responses are correct.
        return true;
    }

    /**
	 * Whether the item of the session has been attempted (at least once). In other words, items which the user has interacted,
	 * whether or not they provided a response.
	 *
	 * @return boolean
	 */
    public function isPresented()
    {
        return $this['numAttempts']->getValue() > 0;
    }

    /**
	 * Whether the item of the session has been selected for presentation to the candidate, regardless of whether the candidate
	 * has attempted them or not.
	 *
	 * @return boolean
	 */
    public function isSelected()
    {
        return true;
    }

    /**
	 * Whether the item of the session has been attempted (at least once) and for which at least one response was given.
	 *
	 * @return boolean
	 */
    public function isResponded()
    {
        if ($this->isPresented() === false) {
            return false;
        }

        $excludedResponseVariables = array('numAttempts', 'duration');
        foreach ($this->getKeys() as $k) {
            $var = $this->getVariable($k);

            if ($var instanceof ResponseVariable && in_array($k, $excludedResponseVariables) === false) {

                $currentValue = $var->getValue();
                $currentDefaultValue = $var->getDefaultValue();

                if ($currentValue === null) {
                    if ($currentValue !== $currentDefaultValue) {
                        return true;
                    }
                } elseif ($currentValue instanceof Container && $currentValue->isNull() === true) {
                    if ($currentDefaultValue !== null && $currentDefaultValue->isNull() === false) {
                        return true;
                    }
                } else {
                    if ($currentValue->equals($currentDefaultValue) === false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
	 * Whether a new attempt is possible for this AssessmentItemSession.
	 *
	 * @return boolean
	 */
    public function isAttemptable()
    {
        return $this->getRemainingAttempts() !== 0;
    }

    /**
	 * Whether or not the item has been attempted at least one.
	 *
	 * @return boolean
	 */
    public function isAttempted()
    {
        return $this['numAttempts']->getValue() > 0;
    }

    /**
	 * Whether or not the maximum time limits in force are reached. If there is
	 * no time limits in force, this method systematically returns false.
	 *
	 * @return boolean
	 */
    protected function isMaxTimeReached()
    {
        $reached = false;

        if ($this->hasTimeLimits() && $this->getTimeLimits()->hasMaxTime() === true) {

            if ($this['duration']->getSeconds(true) >= $this->getTimeLimits()->getMaxTime()->getSeconds(true)) {
                $reached = true;
            }
        }

        return $reached;
    }

    /**
	 * Get the ResponseVariable objects contained in the AssessmentItemSession.
	 *
	 * @param boolean $builtIn Whether to include the built-in ResponseVariables ('duration' and 'numAttempts').
	 * @return \qtism\runtime\common\State A State object composed exclusively with ResponseVariable objects.
	 */
    public function getResponseVariables($builtIn = true)
    {
        $state = new State();
        $data = $this->getDataPlaceHolder();

        foreach ($data as $id => $var) {
            if ($var instanceof ResponseVariable && ($builtIn === true || in_array($id, array('duration', 'numAttempts')) === false)) {
                $state->setVariable($var);
            }
        }

        return $state;
    }

    /**
	 * Get the OutcomeVariable objects contained in the AssessmentItemSession.
	 *
	 * @param boolean $builtIn Whether to include the built-in OutcomeVariable 'completionStatus'.
	 * @return \qtism\runtime\common\State A State object composed exclusively with OutcomeVariable objects.
	 */
    public function getOutcomeVariables($builtIn = true)
    {
        $state = new State();
        $data = $this->getDataPlaceHolder();

        foreach ($data as $id => $var) {
            if ($var instanceof OutcomeVariable && ($builtIn === true || $id !== 'completionStatus')) {
                $state->setVariable($var);
            }
        }

        return $state;
    }

    /**
	 * This protected method contains the logic of creating a new ResponseProcessingEngine object.
	 *
	 * @param \qtism\data\processing\ResponseProcessing $responseProcessing
	 * @return \qtism\runtime\processing\ResponseProcessingEngine
	 */
    protected function createResponseProcessingEngine(ResponseProcessing $responseProcessing)
    {
        return new ResponseProcessingEngine($responseProcessing, $this);
    }

    /**
	 * @see \qtism\common\collections\AbstractCollection::__clone()
	 */
    public function __clone()
    {
        $newData = array();
        $oldData = $this->getDataPlaceHolder();

        foreach ($oldData as $k => $v) {
            $newData[$k] = clone $v;
        }

        $this->setDataPlaceHolder($newData);
    }
}