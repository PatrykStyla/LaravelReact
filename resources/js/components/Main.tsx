import * as React from 'react'
import { 
  Route,
  Switch,
  withRouter,
  RouteComponentProps,
 } from "react-router-dom";
import Welcome from "./Pages/Welcome"
import Champions from "./Pages/Champions"

import Footer from './Footer/Footer';
import Summoner from "./Main/Summoner/Summoner";
import NavigationBar from "./NavigationBar/NavigationBar";

import {MainProps, MainState} from "./ReactInterfaces/RootInterface";
import { Lookup } from './Main/Lookup/Lookup';

class Main extends React.Component<RouteComponentProps<MainProps>, MainState> {
  constructor(props: RouteComponentProps<MainProps>) {
    super(props);
  }

  render() {
    return (
      <div className="dark">
        <NavigationBar {...this.props}/>
        <Switch>
          <Route exact path="/" component={Welcome} />
          <Route exact path="/champions" component={Champions} />
          <Route exact path="/stats" />
          <Route exact path="/leaderboards" />
          {/* <Route path="/summoner" component={Summoner} /> */}
          <Route
            exact path="/summoner/:region/:name"
            component={Summoner}
          />
          <Route 
            exact path="/lookup/:region/:name/:champion"
            component={Lookup}
          />

          {/* <Route path="*" component={NotFound} /> */}
          {/* If we want to pass parameters use the render tag */}
          <Route
            path='*'
            render={(props) => <NotFound {...props} isAuthed={true} />}
          />
        </Switch>
        <Footer />
      </div>
    );
  }
}

export default withRouter(Main);

// TODO props
function NotFound(props: any) {
  return (
    <div>
      The page {props.location.pathname.slice(1)} does not exist.
    </div>
  )
}