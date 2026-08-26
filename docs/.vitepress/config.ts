export default {
  lang: 'en-US',
  title: 'Trustindex Reviews Documentation',
  description: 'Architecture, developer workflow, quality gates and generated code reference for the Symfony review application.',
  outDir: './dist',
  cleanUrls: true,
  ignoreDeadLinks: [/^http:\/\/127\.0\.0\.1:/, /^http:\/\/localhost:/],
  srcExclude: ['dist/**'],
  markdown: { lineNumbers: true },
  themeConfig: {
    search: { provider: 'local' },
    nav: [
      { text: 'Overview', link: '/' },
      { text: 'Developer Guide', link: '/DEVELOPER_GUIDE' },
      { text: 'Architecture', link: '/architecture/' },
      { text: 'Quality', link: '/testing' },
      { text: 'Operations', link: '/operations' },
      { text: 'Reference', link: '/reference/' },
    ],
    sidebar: [
      {
        text: 'Start here',
        items: [
          { text: 'System overview', link: '/' },
          { text: 'Developer Guide', link: '/DEVELOPER_GUIDE' },
          { text: 'Documentation standards', link: '/documentation-standards' },
          { text: 'Documentation runtime', link: '/documentation-runtime' },
        ],
      },
      {
        text: 'System design',
        items: [
          { text: 'Architecture', link: '/architecture/' },
          { text: 'Domain model', link: '/domain-model' },
          { text: 'Request flows', link: '/request-flows' },
        ],
      },
      {
        text: 'Development and quality',
        items: [
          { text: 'Testing strategy', link: '/testing' },
          { text: 'Operations and troubleshooting', link: '/operations' },
        ],
      },
      {
        text: 'Generated reference',
        items: [
          { text: 'Reference hub', link: '/reference/' },
          { text: 'Developer handbook', link: '/DEVELOPER_HANDBOOK' },
          { text: 'Code reference', link: '/code-reference/' },
        ],
      },
    ],
  },
};
