let user = window.App.user;

module.exports = {
    owns (model, prop = 'user_id') {
      return model[prop] == user.id;
    },
    // updateReply (reply) {
    //     return reply.user_id === user.id;
    // }
};