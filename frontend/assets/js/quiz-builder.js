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

  const container = document.getElementById("questions-container")
  const questionCard = document.createElement("div")
  questionCard.className = "question-card"
  questionCard.dataset.questionId = questionCount

  questionCard.innerHTML = `
        <div class="question-card-header">
            <span class="question-number">Question ${questionCount}</span>
            <div class="question-actions">
                <button type="button" class="btn-icon" onclick="moveQuestionUp(${questionCount})" title="Move Up">↑</button>
                <button type="button" class="btn-icon" onclick="moveQuestionDown(${questionCount})" title="Move Down">↓</button>
                <button type="button" class="btn-icon btn-danger" onclick="deleteQuestion(${questionCount})" title="Delete">×</button>
            </div>
        </div>
        
        <div class="question-type-select">
            <label>Question Type:</label>
            <select onchange="changeQuestionType(${questionCount}, this.value)">
                <option value="multiple_choice">Multiple Choice</option>
                <option value="true_false">True/False</option>
                <option value="short_answer">Short Answer</option>
                <option value="essay">Essay</option>
            </select>
        </div>
        
        <textarea class="question-text-input" placeholder="Enter your question here..." required></textarea>
        
        <div class="question-points">
            <label>Points:</label>
            <input type="number" value="1" min="0" step="0.5">
        </div>
        
        <div class="question-options-container">
            ${getQuestionOptionsHTML(type, questionCount)}
        </div>
        
        <div class="question-explanation">
            <label>Explanation (optional):</label>
            <textarea placeholder="Provide an explanation for the correct answer..."></textarea>
        </div>
    `

  container.appendChild(questionCard)
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
