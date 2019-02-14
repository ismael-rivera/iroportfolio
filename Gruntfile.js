module.exports = function(grunt) {

  // Project configuration.
grunt.initConfig({
  compass: {                  // Task
    dev: {                    // Another target
      options: {
        sassDir: 'inc/scss',
        cssDir: 'inc/css'
      }
    }
  }
});

  // Load the plugin that provides the "compass" task.
  grunt.loadNpmTasks('grunt-contrib-compass');

  // Default task(s).
  grunt.registerTask('default', ['compass']);

};