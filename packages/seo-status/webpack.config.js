const Encore = require('@symfony/webpack-encore');
const WatchExternalFilesPlugin = require('webpack-watch-files-plugin').default;

if (!Encore.isRuntimeEnvironmentConfigured()) {
  Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}
//https://symfonycasts.com/screencast/stimulus

Encore.setOutputPath('public/assets/')
  .setPublicPath('/assets')
  .addEntry('app', './assets/app.js')
  .splitEntryChunks()
  .enableSingleRuntimeChunk()
  .cleanupOutputBeforeBuild()
  .enableSourceMaps(!Encore.isProduction())
  .enableVersioning(Encore.isProduction());
var config = Encore.getWebpackConfig();
config.watchOptions = {
  poll: true,
};
module.exports = config;
