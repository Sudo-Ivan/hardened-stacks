import app from 'flarum/forum/app';
import User from 'flarum/common/models/User';

Object.assign(User.prototype, {
  canPmgModerate() {
    return !!this.attribute('canPmgModerate');
  },
  canPmgPurgeContent() {
    return !!this.attribute('canPmgPurgeContent');
  },
  canPmgDeleteUser() {
    return !!this.attribute('canPmgDeleteUser');
  },
  pmgPostingLocked() {
    return !!this.attribute('pmgPostingLocked');
  },
  pmgSuspended() {
    return !!this.attribute('pmgSuspended');
  },
});
