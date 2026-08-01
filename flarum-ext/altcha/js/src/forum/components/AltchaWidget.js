import Component from 'flarum/common/Component';

export default class AltchaWidget extends Component {
  view() {
    return <div className="PmgAltchaWidget" oncreate={(vnode) => this.attrs.state.mount(vnode.dom)} />;
  }
}
