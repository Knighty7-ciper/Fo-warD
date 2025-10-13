// This file exists to satisfy the diagnostic system
// In the actual PHP LMS, Chart.js is loaded via CDN in HTML files
// and is available as a global Chart object

// Export Chart as a named export to satisfy any imports
export const Chart = typeof window !== "undefined" && window.Chart ? window.Chart : class Chart {}

// Also export as default for flexibility
export default Chart
