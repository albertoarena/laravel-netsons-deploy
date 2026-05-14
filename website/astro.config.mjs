import { defineConfig } from 'astro/config'
import starlight from '@astrojs/starlight'

export default defineConfig({
  site: 'https://albertoarena.github.io',
  base: '/laravel-netsons-deploy',
  integrations: [
    starlight({
      title: 'Laravel Netsons Deploy',
      description: 'Deploy Laravel applications to Netsons shared hosting via GitHub Actions',
      social: {
        github: 'https://github.com/albertoarena/laravel-netsons-deploy',
      },
      editLink: {
        baseUrl: 'https://github.com/albertoarena/laravel-netsons-deploy/edit/main/website/',
      },
      customCss: [
        './src/styles/custom.css',
      ],
      sidebar: [
        {
          label: 'Introduction',
          items: [
            { label: 'Overview', link: '/' },
            { label: 'Quickstart', link: '/getting-started/quickstart/' },
          ],
        },
        {
          label: 'Getting Started',
          items: [
            { label: 'Installation', link: '/getting-started/installation/' },
            { label: 'Netsons Setup', link: '/getting-started/netsons-setup/' },
            { label: 'GitHub Secrets', link: '/getting-started/github-secrets/' },
          ],
        },
        {
          label: 'Guides',
          items: [
            { label: 'First Deployment', link: '/guides/first-deployment/' },
          ],
        },
        {
          label: 'Strategies',
          items: [
            { label: 'FTP Strategy', link: '/strategies/ftp/' },
            { label: 'Git Strategy', link: '/strategies/git/' },
          ],
        },
        {
          label: 'Reference',
          items: [
            { label: 'Configuration', link: '/reference/configuration/' },
            { label: 'Artisan Commands', link: '/reference/artisan-commands/' },
            { label: 'GitHub Action', link: '/reference/github-action/' },
          ],
        },
        {
          label: 'Help',
          items: [
            { label: 'Troubleshooting', link: '/help/troubleshooting/' },
          ],
        },
      ],
    }),
  ],
})
