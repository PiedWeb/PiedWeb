const Encore = require('@symfony/webpack-encore');
const WatchExternalFilesPlugin = require('webpack-watch-files-plugin').default;
const tailwindcss = require('tailwindcss');

const watchFiles = ['./src/templates/**/*.html.twig', './src/templates/*.html.twig'];

var tailwindConfig = require('./assets/tailwind.config.js');
tailwindConfig.content = watchFiles;

if (!Encore.isRuntimeEnvironmentConfigured()) {
  Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}
//https://symfonycasts.com/screencast/stimulus

Encore.setOutputPath('public/assets/')
  .setPublicPath('/assets')
  .addEntry('app', './assets/app.js')
  .addStyleEntry('style', './assets/app.css')
  .splitEntryChunks()
  .enableSingleRuntimeChunk()
  .cleanupOutputBeforeBuild()
  .addPlugin(
    new WatchExternalFilesPlugin({
      files: watchFiles,
    })
  )
  .enablePostCssLoader((options) => {
    options.postcssOptions = {
      plugins: [tailwindcss(tailwindConfig)],
    };
  })
  .enableSourceMaps(!Encore.isProduction())
  .enableVersioning(Encore.isProduction());
var config = Encore.getWebpackConfig();
config.watchOptions = {
  poll: true,
};
module.exports = config;
