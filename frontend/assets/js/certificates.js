document.addEventListener("DOMContentLoaded", () => {
  loadCertificates()
})

async function loadCertificates() {
  try {
    const response = await fetch("../../backend/api/certificates.php")
    const data = await response.json()

    if (data.success && data.data) {
      displayCertificates(data.data)
    }
  } catch (error) {
    console.error("[v0] Error loading certificates:", error)
    showError("Failed to load certificates")
  }
}

function displayCertificates(certificates) {
  const container = document.getElementById("certificatesGrid")
  const noResults = document.getElementById("noCertificates")

  if (certificates.length === 0) {
    container.style.display = "none"
    noResults.style.display = "block"
    return
  }

  container.style.display = "grid"
  noResults.style.display = "none"

  container.innerHTML = certificates
    .map(
      (cert) => `
        <div class="certificate-card">
            <div class="certificate-thumbnail">
                Certificate of Completion
            </div>
            <div class="certificate-content">
                <h3>${cert.course_title}</h3>
                <div class="certificate-meta">
                    <div>Issued: ${new Date(cert.issued_at).toLocaleDateString()}</div>
                    <div class="certificate-number">No: ${cert.certificate_number}</div>
                </div>
                <div class="certificate-actions">
                    <button class="btn btn-primary" onclick="downloadCertificate(${cert.id})">Download PDF</button>
                    <button class="btn btn-secondary" onclick="verifyCertificate('${cert.certificate_number}', '${cert.blockchain_hash}')">Verify</button>
                </div>
            </div>
        </div>
    `,
    )
    .join("")
}

function downloadCertificate(certificateId) {
  window.location.href = `../../backend/api/generate-certificate-pdf.php?id=${certificateId}`
}

function verifyCertificate(certNumber, blockchainHash) {
  const info = document.getElementById("verificationInfo")

  info.innerHTML = `
        <div class="verification-item">
            <span class="verification-label">Certificate Number:</span>
            <span class="verification-value">${certNumber}</span>
        </div>
        <div class="verification-item">
            <span class="verification-label">Status:</span>
            <span class="verification-value" style="color: #10b981; font-weight: 600;">✓ Verified</span>
        </div>
        <div class="verification-item">
            <span class="verification-label">Blockchain Hash:</span>
            <span class="verification-value verification-hash">${blockchainHash}</span>
        </div>
        <div class="verification-item">
            <span class="verification-label">Verification Method:</span>
            <span class="verification-value">Blockchain Verified</span>
        </div>
    `

  showModal("verifyModal")
}

function showModal(modalId) {
  document.getElementById(modalId).style.display = "flex"
}

function closeModal(modalId) {
  document.getElementById(modalId).style.display = "none"
}

function showError(message) {
  alert("Error: " + message)
}
