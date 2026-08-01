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
    if (this.attribute('pmgPostingLocked') !== undefined) {
      return !!this.attribute('pmgPostingLocked');
    }

    return !!this.preferences()?.pmgPostingLocked;
  },
  pmgSuspended() {
    if (this.attribute('pmgSuspended') !== undefined) {
      return !!this.attribute('pmgSuspended');
    }

    const until = this.preferences()?.pmgSuspendedUntil;
    if (!until) {
      return false;
    }

    if (until === 'forever') {
      return true;
    }

    const parsed = Date.parse(until);
    return !Number.isNaN(parsed) && parsed > Date.now();
  },
});
