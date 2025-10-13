let currentCategory = null

document.addEventListener("DOMContentLoaded", () => {
  if (window.location.pathname.includes("/help/index.php") || window.location.pathname.endsWith("/help/")) {
    loadArticles()
  }
})

function loadArticles(category = null) {
  currentCategory = category

  let url = "/backend/api/help.php"
  if (category) {
    url += `?category=${encodeURIComponent(category)}`
  }

  fetch(url)
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        renderArticles(data.articles)
      }
    })
    .catch((err) => console.error("Error loading articles:", err))
}

function renderArticles(articles) {
  const container = document.getElementById("articlesList")
  let html = ""

  articles.forEach((article) => {
    html += '<div class="article-item" onclick="viewArticle(\'' + article.slug + "')\">"
    html += `<h3>${article.title}</h3>`
    html += `<p>${article.content.substring(0, 150)}...</p>`
    html += '<div class="article-meta">'
    html += `<span><i class="fas fa-eye"></i> ${article.views} views</span>`
    html += `<span><i class="fas fa-tag"></i> ${article.category}</span>`
    html += "</div>"
    html += "</div>"
  })

  container.innerHTML = html || '<p class="text-center text-muted">No articles found</p>'
}

function searchHelp() {
  const query = document.getElementById("helpSearch").value

  if (query.length < 2) {
    loadArticles(currentCategory)
    return
  }

  fetch(`/backend/api/help.php?action=search&q=${encodeURIComponent(query)}`)
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        renderArticles(data.articles)
      }
    })
}

function filterByCategory(category) {
  loadArticles(category)
}

function viewArticle(slug) {
  window.location.href = `/frontend/help/article.php?slug=${slug}`
}

function loadArticle(slug) {
  fetch(`/backend/api/help.php?slug=${slug}`)
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        const article = data.article
        document.getElementById("articleTitle").textContent = article.title
        document.getElementById("articleCategory").textContent = article.category
        document.getElementById("articleDate").textContent = new Date(article.created_at).toLocaleDateString()
        document.getElementById("articleViews").textContent = `${article.views} views`
        document.getElementById("articleBody").innerHTML = article.content
      }
    })
    .catch((err) => console.error("Error loading article:", err))
}

function submitFeedback(isHelpful) {
  const articleId = new URLSearchParams(window.location.search).get("id")

  fetch("/backend/api/help.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      feedback: true,
      article_id: articleId,
      is_helpful: isHelpful,
    }),
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        const message = document.getElementById("feedbackMessage")
        message.textContent = data.message
        message.style.display = "block"
        message.className = "alert alert-success"

        document.querySelector(".feedback-buttons").style.display = "none"
      }
    })
}
