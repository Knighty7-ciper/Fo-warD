let questionCount = 0

document.addEventListener("DOMContentLoaded", () => {
  loadTeacherCourses()
})

async function loadTeacherCourses() {
  try {
    const response = await fetch("/backend/api/courses.php?teacher_courses=1")
    const courses = await response.json()

    const select = document.querySelector('select[name="course_id"]')
    select.innerHTML =
      '<option value="">Select Course</option>' +
      courses.map((c) => `<option value="${c.id}">${c.title}</option>`).join("")

    // Pre-select if course_id in URL
    const urlParams = new URLSearchParams(window.location.search)
    const courseId = urlParams.get("course_id")
    if (courseId) {
      select.value = courseId
    }
  } catch (error) {
    console.error("Error loading courses:", error)
  }
}

function addQuestion(type = "multiple_choice") {
  questionCount++

  // Hide empty state and show questions list
  document.getElementById('questionsEmpty').style.display = 'none'
  document.getElementById('questionsList').style.display = 'block'

  const container = document.getElementById('questionsList')
  const questionCard = document.createElement('div')
  questionCard.className = 'question-card'
  questionCard.dataset.questionId = questionCount

  questionCard.innerHTML = `
        <div class="question-card-header">
            <div class="question-number">
                <i class="fas fa-question-circle"></i> Question ${questionCount}
            </div>
            <div class="question-actions">
                <button type="button" class="btn btn-sm btn-icon" onclick="duplicateQuestion(${questionCount})" title="Duplicate">
                    <i class="fas fa-copy"></i>
                </button>
                <button type="button" class="btn btn-sm btn-icon" onclick="moveQuestionUp(${questionCount})" title="Move Up">
                    <i class="fas fa-arrow-up"></i>
                </button>
                <button type="button" class="btn btn-sm btn-icon" onclick="moveQuestionDown(${questionCount})" title="Move Down">
                    <i class="fas fa-arrow-down"></i>
                </button>
                <button type="button" class="btn btn-sm btn-icon text-danger" onclick="deleteQuestion(${questionCount})" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        
        <div class="question-content">
            <div class="question-type-row">
                <div class="form-group">
                    <label>Question Type:</label>
                    <select onchange="changeQuestionType(${questionCount}, this.value)" class="form-select">
                        <option value="multiple_choice" ${type === 'multiple_choice' ? 'selected' : ''}>Multiple Choice</option>
                        <option value="true_false" ${type === 'true_false' ? 'selected' : ''}>True/False</option>
                        <option value="short_answer" ${type === 'short_answer' ? 'selected' : ''}>Short Answer</option>
                        <option value="essay" ${type === 'essay' ? 'selected' : ''}>Essay</option>
                        <option value="fill_blank" ${type === 'fill_blank' ? 'selected' : ''}>Fill in the Blank</option>
                        <option value="matching" ${type === 'matching' ? 'selected' : ''}>Matching</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Points:</label>
                    <input type="number" class="form-control question-points" value="1" min="0" step="0.5">
                </div>
                <div class="form-group">
                    <label>Required:</label>
                    <input type="checkbox" class="form-check-input question-required" checked>
                </div>
            </div>
            
            <div class="form-group">
                <label>Question Text:</label>
                <textarea class="form-control question-text-input" placeholder="Enter your question here..." required rows="3"></textarea>
            </div>
            
            <div class="question-options-container" id="options-${questionCount}">
                ${getQuestionOptionsHTML(type, questionCount)}
            </div>
            
            <div class="form-group">
                <label>Explanation (optional):</label>
                <textarea class="form-control question-explanation" placeholder="Provide an explanation for the correct answer..." rows="2"></textarea>
            </div>
            
            <div class="question-advanced">
                <button type="button" class="btn btn-sm btn-outline" onclick="toggleAdvancedOptions(${questionCount})">
                    <i class="fas fa-cog"></i> Advanced Options
                </button>
                <div class="advanced-options" id="advanced-${questionCount}" style="display: none;">
                    <div class="form-group">
                        <label>Time Limit (seconds):</label>
                        <input type="number" class="form-control question-time-limit" min="0" placeholder="No limit">
                    </div>
                    <div class="form-group">
                        <label>Attempt Limit:</label>
                        <input type="number" class="form-control question-attempt-limit" min="1" value="1">
                    </div>
                    <div class="form-group">
                        <label>Shuffle Options:</label>
                        <input type="checkbox" class="form-check-input question-shuffle" checked>
                    </div>
                </div>
            </div>
        </div>
    `

  container.appendChild(questionCard)
  
  // Update question counter display
  updateQuestionCounter()
  
  // Scroll to the new question
  questionCard.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

function getQuestionOptionsHTML(type, questionId) {
  if (type === "multiple_choice") {
    return `
            <div class="question-options">
                <label>Options (select the correct answer):</label>
                <div class="options-list" id="options-${questionId}">
                    <div class="option-item">
                        <input type="radio" name="correct-${questionId}" value="0">
                        <input type="text" placeholder="Option 1" required>
                        <button type="button" class="btn-icon btn-danger" onclick="removeOption(this)">×</button>
                    </div>
                    <div class="option-item">
                        <input type="radio" name="correct-${questionId}" value="1">
                        <input type="text" placeholder="Option 2" required>
                        <button type="button" class="btn-icon btn-danger" onclick="removeOption(this)">×</button>
                    </div>
                </div>
                <button type="button" class="add-option-btn" onclick="addOption(${questionId})">+ Add Option</button>
            </div>
        `
  } else if (type === "true_false") {
    return `
            <div class="question-options">
                <label>Correct Answer:</label>
                <div class="options-list">
                    <div class="option-item">
                        <input type="radio" name="correct-${questionId}" value="true" checked>
                        <label>True</label>
                    </div>
                    <div class="option-item">
                        <input type="radio" name="correct-${questionId}" value="false">
                        <label>False</label>
                    </div>
                </div>
            </div>
        `
  } else {
    return `<p style="color: #999; font-style: italic;">Students will provide a text answer for this question.</p>`
  }
}

function changeQuestionType(questionId, type) {
  const card = document.querySelector(`[data-question-id="${questionId}"]`)
  const container = card.querySelector(".question-options-container")
  container.innerHTML = getQuestionOptionsHTML(type, questionId)
}

function addOption(questionId) {
  const optionsList = document.getElementById(`options-${questionId}`)
  const optionCount = optionsList.children.length

  const optionItem = document.createElement("div")
  optionItem.className = "option-item"
  optionItem.innerHTML = `
        <input type="radio" name="correct-${questionId}" value="${optionCount}">
        <input type="text" placeholder="Option ${optionCount + 1}" required>
        <button type="button" class="btn-icon btn-danger" onclick="removeOption(this)">×</button>
    `

  optionsList.appendChild(optionItem)
}

function removeOption(button) {
  const optionItem = button.closest(".option-item")
  const optionsList = optionItem.parentElement

  if (optionsList.children.length > 2) {
    optionItem.remove()
  } else {
    alert("A question must have at least 2 options")
  }
}

function deleteQuestion(questionId) {
  if (confirm("Are you sure you want to delete this question?")) {
    const card = document.querySelector(`[data-question-id="${questionId}"]`)
    card.remove()
    renumberQuestions()
  }
}

function moveQuestionUp(questionId) {
  const card = document.querySelector(`[data-question-id="${questionId}"]`)
  const prev = card.previousElementSibling

  if (prev) {
    card.parentElement.insertBefore(card, prev)
    renumberQuestions()
  }
}

function moveQuestionDown(questionId) {
  const card = document.querySelector(`[data-question-id="${questionId}"]`)
  const next = card.nextElementSibling

  if (next) {
    card.parentElement.insertBefore(next, card)
    renumberQuestions()
  }
}

function renumberQuestions() {
  const cards = document.querySelectorAll(".question-card")
  cards.forEach((card, index) => {
    card.querySelector(".question-number").textContent = `Question ${index + 1}`
  })
}

async function saveDraft() {
  await saveQuiz("draft")
}

async function publishQuiz() {
  await saveQuiz("published")
}

async function saveQuiz(status) {
  const form = document.getElementById("quiz-form")
  const formData = new FormData(form)

  // Collect quiz data
  const quizData = {
    course_id: formData.get("course_id"),
    title: formData.get("title"),
    description: formData.get("description"),
    instructions: formData.get("instructions"),
    time_limit: Number.parseInt(formData.get("time_limit")),
    passing_score: Number.parseFloat(formData.get("passing_score")),
    max_attempts: Number.parseInt(formData.get("max_attempts")),
    shuffle_questions: formData.get("shuffle_questions") === "on",
    show_correct_answers: formData.get("show_correct_answers") === "on",
    show_results_immediately: formData.get("show_results_immediately") === "on",
    status: status,
    questions: [],
  }

  // Collect questions
  const questionCards = document.querySelectorAll(".question-card")
  questionCards.forEach((card) => {
    const typeSelect = card.querySelector(".question-type-select select")
    const questionType = typeSelect.value
    const questionText = card.querySelector(".question-text-input").value
    const points = Number.parseFloat(card.querySelector(".question-points input").value)
    const explanation = card.querySelector(".question-explanation textarea").value

    const question = {
      type: questionType,
      text: questionText,
      points: points,
      explanation: explanation,
      options: [],
    }

    // Collect options for multiple choice
    if (questionType === "multiple_choice") {
      const optionItems = card.querySelectorAll(".option-item")
      optionItems.forEach((item, index) => {
        const optionText = item.querySelector('input[type="text"]').value
        const isCorrect = item.querySelector('input[type="radio"]').checked

        question.options.push({
          text: optionText,
          is_correct: isCorrect,
        })
      })
    } else if (questionType === "true_false") {
      const correctAnswer = card.querySelector('input[type="radio"]:checked').value
      question.options.push({
        text: "True",
        is_correct: correctAnswer === "true",
      })
      question.options.push({
        text: "False",
        is_correct: correctAnswer === "false",
      })
    }

    quizData.questions.push(question)
  })

  try {
    const response = await fetch("/backend/api/quizzes.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(quizData),
    })

    const result = await response.json()

    if (result.success) {
      alert(`Quiz ${status === "published" ? "published" : "saved as draft"} successfully!`)
      window.location.href = "/frontend/teacher/quizzes.php"
    } else {
      alert("Error saving quiz: " + (result.error || "Unknown error"))
    }
  } catch (error) {
    console.error("Error saving quiz:", error)
    alert("Failed to save quiz")
  }
}

// Helper functions for question management

function updateQuestionCounter() {
  const questionCount = document.querySelectorAll('.question-card').length
  // Update any UI elements that show question count
  const counters = document.querySelectorAll('.question-counter')
  counters.forEach(counter => {
    counter.textContent = `Questions (${questionCount})`
  })
}

function duplicateQuestion(questionId) {
  const originalCard = document.querySelector(`[data-question-id="${questionId}"]`)
  if (!originalCard) return
  
  // Clone the question card
  const clonedCard = originalCard.cloneNode(true)
  
  // Update question ID and number
  questionCount++
  clonedCard.dataset.questionId = questionCount
  
  // Update question number display
  const questionNumber = clonedCard.querySelector('.question-number')
  questionNumber.innerHTML = `<i class="fas fa-question-circle"></i> Question ${questionCount}`
  
  // Update all function calls in the cloned card
  const clonedHTML = clonedCard.innerHTML
  const updatedHTML = clonedHTML
    .replace(new RegExp(`(${questionId})`, 'g'), questionCount)
    .replace(/onclick="duplicateQuestion\(\d+\)"/g, `onclick="duplicateQuestion(${questionCount})"`)
    .replace(/onclick="moveQuestionUp\(\d+\)"/g, `onclick="moveQuestionUp(${questionCount})"`)
    .replace(/onclick="moveQuestionDown\(\d+\)"/g, `onclick="moveQuestionDown(${questionCount})"`)
    .replace(/onclick="deleteQuestion\(\d+\)"/g, `onclick="deleteQuestion(${questionCount})"`)
    .replace(/onclick="changeQuestionType\(\d+\)/g, `onclick="changeQuestionType(${questionCount}`)
    .replace(/onclick="toggleAdvancedOptions\(\d+\)/g, `onclick="toggleAdvancedOptions(${questionCount})"`)
    .replace(/id="options-${originalCard.dataset.questionId}"/g, `id="options-${questionCount}"`)
    .replace(/id="advanced-${originalCard.dataset.questionId}"/g, `id="advanced-${questionCount}"`)
    .replace(new RegExp(`name="correct-${originalCard.dataset.questionId}"`, 'g'), `name="correct-${questionCount}"`)
  
  clonedCard.innerHTML = updatedHTML
  
  // Add to container
  const container = document.getElementById('questionsList')
  container.appendChild(clonedCard)
  
  updateQuestionCounter()
  clonedCard.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

function toggleAdvancedOptions(questionId) {
  const advancedDiv = document.getElementById(`advanced-${questionId}`)
  const button = event.target
  
  if (advancedDiv.style.display === 'none') {
    advancedDiv.style.display = 'block'
    button.innerHTML = '<i class="fas fa-cog"></i> Hide Advanced'
  } else {
    advancedDiv.style.display = 'none'
    button.innerHTML = '<i class="fas fa-cog"></i> Advanced Options'
  }
}

function validateQuiz() {
  const questions = document.querySelectorAll('.question-card')
  if (questions.length === 0) {
    alert('Please add at least one question to the quiz.')
    return false
  }
  
  for (let i = 0; i < questions.length; i++) {
    const question = questions[i]
    const questionText = question.querySelector('.question-text-input').value.trim()
    
    if (!questionText) {
      alert(`Question ${i + 1}: Question text is required.`)
      return false
    }
    
    const questionType = question.querySelector('select').value
    
    // Validate options for multiple choice questions
    if (questionType === 'multiple_choice') {
      const options = question.querySelectorAll('.option-item')
      let hasCorrectAnswer = false
      
      for (let option of options) {
        const optionText = option.querySelector('input[type="text"]').value.trim()
        const isCorrect = option.querySelector('input[type="radio"]').checked
        
        if (!optionText) {
          alert(`Question ${i + 1}: All options must have text.`)
          return false
        }
        
        if (isCorrect) hasCorrectAnswer = true
      }
      
      if (!hasCorrectAnswer) {
        alert(`Question ${i + 1}: Please select a correct answer.`)
        return false
      }
    }
  }
  
  return true
}
